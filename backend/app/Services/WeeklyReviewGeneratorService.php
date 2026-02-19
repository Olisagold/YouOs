<?php

namespace App\Services;

use App\AI\Prompts\WeeklyReviewPromptBuilder;
use App\Models\Doctrine;
use App\Models\WeeklyReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WeeklyReviewGeneratorService
{
    public function __construct(
        private readonly OpenRouterService $openRouterService,
        private readonly WeeklyReviewPromptBuilder $weeklyReviewPromptBuilder
    ) {}

    public function generate(User $user, Doctrine $doctrine): WeeklyReview
    {
        [$weekStart, $weekEnd] = $this->resolveRange();

        $dailyCheckins = $user->dailyCheckins()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->orderBy('created_at')
            ->get();

        $decisions = $user->decisions()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->orderBy('created_at')
            ->get();

        $disciplineLogs = $user->disciplineLogs()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->orderBy('created_at')
            ->get();

        $messages = $this->weeklyReviewPromptBuilder->build(
            $doctrine,
            $dailyCheckins,
            $decisions,
            $disciplineLogs,
            $weekStart,
            $weekEnd
        );

        $chatResponse = $this->openRouterService->requestChatCompletion(
            $messages['system'],
            $messages['user'],
            0.2,
            1200
        );

        $decodedContent = $this->openRouterService->decodeJsonContent($chatResponse['content']);
        $validated = $this->validateReviewSchema($decodedContent);

        return WeeklyReview::updateOrCreate(
            [
                'user_id' => $user->id,
                'week_start' => $weekStart->toDateString(),
            ],
            [
                'week_end' => $weekEnd->toDateString(),
                'ai_review_json' => $validated,
            ]
        );
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function resolveRange(): array
    {
        $now = CarbonImmutable::now();
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $weekEnd = $now->endOfDay();

        return [$weekStart, $weekEnd];
    }

    private function validateReviewSchema(array $payload): array
    {
        $expectedKeys = [
            'week_summary',
            'compliance_rate',
            'patterns_detected',
            'strongest_day',
            'weakest_day',
            'override_analysis',
            'directive',
            'doctrine_alignment_score',
        ];

        $actualKeys = array_keys($payload);
        sort($expectedKeys);
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys) {
            throw ValidationException::withMessages([
                'ai_response' => ['AI response must contain exactly the expected weekly review keys.'],
            ]);
        }

        return Validator::make($payload, [
            'week_summary' => ['required', 'string', 'min:1'],
            'compliance_rate' => ['required', 'numeric', 'between:0,1'],
            'patterns_detected' => ['required', 'array'],
            'patterns_detected.*' => ['required', 'string', 'min:1'],
            'strongest_day' => ['required', 'string', 'min:1'],
            'weakest_day' => ['required', 'string', 'min:1'],
            'override_analysis' => ['required', 'string', 'min:1'],
            'directive' => ['required', 'string', 'min:1'],
            'doctrine_alignment_score' => ['required', 'integer', 'between:0,100'],
        ])->validate();
    }
}
