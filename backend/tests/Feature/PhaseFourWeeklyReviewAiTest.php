<?php

namespace Tests\Feature;

use App\Exceptions\OpenRouterUnavailableException;
use App\Models\Doctrine;
use App\Models\User;
use App\Services\OpenRouterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class PhaseFourWeeklyReviewAiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_review_generation_is_blocked_without_doctrine(): void
    {
        CarbonImmutable::setTestNow('2026-02-18 09:15:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/weekly-review/generate')
            ->assertStatus(422)
            ->assertJsonPath('error', 'doctrine_required');

        $this->assertDatabaseCount('weekly_reviews', 0);
    }

    public function test_weekly_review_saves_valid_json_schema(): void
    {
        CarbonImmutable::setTestNow('2026-02-18 09:15:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seedDoctrine($user);
        $this->seedWeeklyData($user);

        $expectedAiReview = [
            'week_summary' => 'Solid execution with one avoidable override.',
            'compliance_rate' => 0.75,
            'patterns_detected' => ['Strong mornings', 'Override after low sleep'],
            'strongest_day' => 'Monday',
            'weakest_day' => 'Tuesday',
            'override_analysis' => 'Overrides were linked to fatigue and urgency bias.',
            'directive' => 'Protect sleep and delay high-risk decisions when energy is below 6.',
            'doctrine_alignment_score' => 87,
        ];

        $this->mock(OpenRouterService::class, function (MockInterface $mock) use ($expectedAiReview): void {
            $mock->shouldReceive('requestChatCompletion')
                ->once()
                ->andReturn([
                    'raw' => '{"id":"chatcmpl-test"}',
                    'content' => json_encode($expectedAiReview),
                ]);

            $mock->shouldReceive('decodeJsonContent')
                ->once()
                ->andReturn($expectedAiReview);
        });

        $this->postJson('/api/v1/weekly-review/generate')
            ->assertCreated();

        $this->assertDatabaseHas('weekly_reviews', [
            'user_id' => $user->id,
            'week_start' => '2026-02-16 00:00:00',
            'week_end' => '2026-02-18 00:00:00',
        ]);

        $review = $user->weeklyReviews()->firstOrFail();

        $this->assertSame('Solid execution with one avoidable override.', $review->ai_review_json['week_summary']);
        $this->assertSame(87, $review->ai_review_json['doctrine_alignment_score']);
        $this->assertSame(0.75, (float) $review->ai_review_json['compliance_rate']);
    }

    public function test_invalid_json_response_is_rejected_and_not_saved(): void
    {
        CarbonImmutable::setTestNow('2026-02-18 09:15:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seedDoctrine($user);
        $this->seedWeeklyData($user);

        $this->mock(OpenRouterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('requestChatCompletion')
                ->once()
                ->andReturn([
                    'raw' => '{"id":"chatcmpl-test"}',
                    'content' => 'not-json',
                ]);

            $mock->shouldReceive('decodeJsonContent')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'ai_response' => ['AI response is not valid JSON.'],
                ]));
        });

        $this->postJson('/api/v1/weekly-review/generate')
            ->assertStatus(422)
            ->assertJsonPath('error', 'ai_response_invalid');

        $this->assertDatabaseCount('weekly_reviews', 0);
    }

    public function test_openrouter_unavailable_returns_503(): void
    {
        CarbonImmutable::setTestNow('2026-02-18 09:15:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seedDoctrine($user);
        $this->seedWeeklyData($user);

        $this->mock(OpenRouterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('requestChatCompletion')
                ->once()
                ->andThrow(new OpenRouterUnavailableException(
                    'OpenRouter returned a non-success status code.',
                    503,
                    '{"error":"upstream"}'
                ));
        });

        $this->postJson('/api/v1/weekly-review/generate')
            ->assertStatus(503)
            ->assertJsonPath('error', 'openrouter_unavailable');

        $this->assertDatabaseCount('weekly_reviews', 0);
    }

    private function seedDoctrine(User $user): Doctrine
    {
        return Doctrine::create([
            'user_id' => $user->id,
            'goals_json' => [
                ['rank' => 1, 'goal' => 'Execute doctrine every day'],
                ['rank' => 2, 'goal' => 'Compound disciplined decisions'],
            ],
            'rules_json' => [
                'No impulsive high-risk commitments',
                'Protect deep work mornings',
            ],
            'habits_json' => [
                ['habit' => 'Check plan at 07:00', 'trigger' => 'After hydration'],
            ],
            'weekly_targets_json' => [
                ['target' => 'Deep work blocks', 'metric' => 'count', 'current' => 3, 'goal' => 10],
            ],
        ]);
    }

    private function seedWeeklyData(User $user): void
    {
        $user->dailyCheckins()->create([
            'checkin_date' => '2026-02-16',
            'energy' => 8,
            'mood' => 7,
            'missions_json' => ['Ship backend endpoint', 'Workout', 'Review doctrine'],
            'notes' => 'Strong start.',
            'created_at' => CarbonImmutable::parse('2026-02-16 08:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-02-16 08:00:00'),
        ]);

        $user->dailyCheckins()->create([
            'checkin_date' => '2026-02-18',
            'energy' => 6,
            'mood' => 6,
            'missions_json' => ['Finalize tests', 'Walk', 'Weekly synthesis'],
            'notes' => 'Moderate energy.',
            'created_at' => CarbonImmutable::parse('2026-02-18 08:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-02-18 08:00:00'),
        ]);

        $decision = $user->decisions()->create([
            'category' => 'work',
            'context_json' => [
                'what' => 'Take additional client request',
                'why' => 'Increase revenue',
                'when' => 'This week',
                'urgency' => 'medium',
                'estimated_impact' => 'Potential schedule strain',
                'alternatives' => ['Delay to next sprint'],
            ],
            'ai_response_json' => [
                'verdict' => 'delay',
                'confidence' => 74,
                'reasoning' => ['Current missions are already overloaded', 'Risk to doctrine priorities'],
                'risks' => ['Quality drop'],
                'better_option' => 'Delay and revisit after current commitments close.',
                'next_steps' => ['Re-assess Friday', 'Protect top priorities'],
            ],
            'final_choice' => 'Accepted anyway',
            'outcome_notes' => 'Worked late to complete everything.',
            'created_at' => CarbonImmutable::parse('2026-02-17 12:00:00'),
            'updated_at' => CarbonImmutable::parse('2026-02-17 12:00:00'),
        ]);

        $user->disciplineLogs()->create([
            'decision_id' => $decision->id,
            'log_type' => 'override',
            'reason' => 'Felt pressure to say yes.',
            'created_at' => CarbonImmutable::parse('2026-02-17 18:30:00'),
            'updated_at' => CarbonImmutable::parse('2026-02-17 18:30:00'),
        ]);
    }
}
