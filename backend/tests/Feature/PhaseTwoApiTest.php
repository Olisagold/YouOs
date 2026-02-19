<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\Doctrine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseTwoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_two_checkins_in_one_day(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'energy' => 8,
            'mood' => 7,
            'missions_json' => ['Ship API', 'Exercise', 'Read'],
            'notes' => 'Strong focus day.',
        ];

        $this->postJson('/api/v1/checkin', $payload)->assertCreated();

        $this->postJson('/api/v1/checkin', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'daily_checkin_exists');
    }

    public function test_decisions_are_blocked_without_doctrine_and_daily_checkin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $decisionPayload = [
            'category' => 'work',
            'context_json' => [
                'what' => 'Take a new project',
                'why' => 'Growth opportunity',
                'when' => 'Today',
                'urgency' => 'medium',
                'estimated_impact' => 'High leverage for the quarter',
                'alternatives' => ['Decline', 'Defer'],
            ],
        ];

        $this->postJson('/api/v1/decisions', $decisionPayload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'doctrine_required');

        Doctrine::create([
            'user_id' => $user->id,
            'goals_json' => [['rank' => 1, 'goal' => 'Build disciplined system']],
            'rules_json' => ['Do hard things first'],
            'habits_json' => [['habit' => 'Morning planning', 'trigger' => 'After coffee']],
            'weekly_targets_json' => [[
                'target' => 'Deep work hours',
                'metric' => 'hours',
                'current' => 0,
                'goal' => 20,
            ]],
        ]);

        $this->postJson('/api/v1/decisions', $decisionPayload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'daily_checkin_required');
    }

    public function test_doctrine_upsert_updates_existing_record_for_same_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $firstPayload = [
            'goals_json' => [
                ['rank' => 1, 'goal' => 'Become consistent'],
                ['rank' => 2, 'goal' => 'Improve decision quality'],
            ],
            'rules_json' => ['No excuses', 'Do priorities first'],
            'habits_json' => [
                ['habit' => 'Journal', 'trigger' => 'After waking'],
            ],
            'weekly_targets_json' => [
                ['target' => 'Focus blocks', 'metric' => 'count', 'current' => 0, 'goal' => 10],
            ],
        ];

        $this->putJson('/api/v1/doctrine', $firstPayload)->assertOk();
        $this->assertDatabaseCount('doctrines', 1);

        $secondPayload = $firstPayload;
        $secondPayload['rules_json'] = ['Protect mornings'];

        $this->putJson('/api/v1/doctrine', $secondPayload)->assertOk();
        $this->assertDatabaseCount('doctrines', 1);

        $doctrine = Doctrine::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['Protect mornings'], $doctrine->rules_json);
    }

    public function test_ownership_enforcement_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $decision = Decision::create([
            'user_id' => $owner->id,
            'category' => 'financial',
            'context_json' => [
                'what' => 'Allocate savings',
                'why' => 'Long-term resilience',
                'when' => 'This week',
                'urgency' => 'low',
                'estimated_impact' => 'Compounding return',
            ],
            'ai_response_json' => null,
            'final_choice' => null,
            'outcome_notes' => null,
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/v1/decisions/{$decision->id}")
            ->assertForbidden();
    }
}
