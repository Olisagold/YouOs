<?php

namespace Tests\Feature;

use App\Models\Doctrine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseThreeDecisionAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_decision_returns_structured_json(): void
    {
        config(['services.openrouter.api_key' => 'test-openrouter-key']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seedDoctrineAndTodayCheckin($user);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'verdict' => 'approve',
                                'confidence' => 84,
                                'reasoning' => ['Aligned with ranked goals', 'Supports discipline'],
                                'risks' => ['Execution fatigue'],
                                'better_option' => 'Proceed with a smaller first step',
                                'next_steps' => ['Schedule first action', 'Define success metric'],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'category' => 'work',
            'context_json' => [
                'what' => 'Accept a high-impact project',
                'why' => 'Career growth and skill compounding',
                'when' => 'This week',
                'urgency' => 'medium',
                'estimated_impact' => 'High strategic value',
                'alternatives' => ['Defer one sprint'],
            ],
        ];

        $response = $this->postJson('/api/v1/decisions', $payload);

        $response->assertCreated()
            ->assertJsonPath('ai_response.verdict', 'approve')
            ->assertJsonPath('ai_response.confidence', 84)
            ->assertJsonPath('decision.ai_response_json.verdict', 'approve');

        $this->assertDatabaseCount('decisions', 1);
        $this->assertDatabaseHas('decisions', [
            'user_id' => $user->id,
            'category' => 'work',
        ]);

        $decision = $user->decisions()->firstOrFail();
        $this->assertNotNull($decision->raw_ai_response);
    }

    public function test_invalid_ai_response_is_rejected_and_not_saved(): void
    {
        config(['services.openrouter.api_key' => 'test-openrouter-key']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seedDoctrineAndTodayCheckin($user);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"verdict":"maybe","confidence":"high"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'category' => 'health',
            'context_json' => [
                'what' => 'Start a new training block',
                'why' => 'Improve stamina',
                'when' => 'Tomorrow',
                'urgency' => 'low',
                'estimated_impact' => 'Sustained performance gains',
            ],
        ];

        $this->postJson('/api/v1/decisions', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ai_response_invalid');

        $this->assertDatabaseCount('decisions', 0);
    }

    public function test_missing_api_key_returns_openrouter_unavailable_error(): void
    {
        config(['services.openrouter.api_key' => null]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seedDoctrineAndTodayCheckin($user);

        $payload = [
            'category' => 'mindset',
            'context_json' => [
                'what' => 'Commit to a stricter routine',
                'why' => 'Reduce impulsive behavior',
                'when' => 'Today',
                'urgency' => 'high',
                'estimated_impact' => 'Improved consistency',
            ],
        ];

        $this->postJson('/api/v1/decisions', $payload)
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'openrouter_unavailable');

        $this->assertDatabaseCount('decisions', 0);
    }

    private function seedDoctrineAndTodayCheckin(User $user): void
    {
        Doctrine::create([
            'user_id' => $user->id,
            'goals_json' => [
                ['rank' => 1, 'goal' => 'Live by doctrine daily'],
                ['rank' => 2, 'goal' => 'Make better long-term decisions'],
            ],
            'rules_json' => [
                'Do hard things first',
                'Protect focus windows',
            ],
            'habits_json' => [
                ['habit' => 'Morning planning', 'trigger' => 'After wake-up'],
            ],
            'weekly_targets_json' => [
                ['target' => 'Deep work', 'metric' => 'hours', 'current' => 4, 'goal' => 20],
            ],
        ]);

        $user->dailyCheckins()->create([
            'checkin_date' => CarbonImmutable::today()->toDateString(),
            'energy' => 8,
            'mood' => 7,
            'missions_json' => ['Execute top priority', 'Exercise', 'Reflect'],
            'notes' => 'Focused and stable.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
