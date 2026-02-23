<?php

namespace App\Services\Chat\Handlers;

use App\Models\Conversation;
use App\Models\DailyCheckin;
use App\Models\Decision;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;
use App\Services\OpenRouterService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DecisionChatHandler implements ChatHandlerInterface
{
    public function __construct(
        private readonly OpenRouterService $openRouterService
    ) {}

    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array {
        $doctrine = $user->doctrine()->first();

        if (! $doctrine) {
            return [
                'assistant_message' => 'Sign in required setup: doctrine must be set before decision analysis.',
                'structured_json' => [
                    'error' => 'doctrine_required',
                ],
                'pending_action' => null,
            ];
        }

        $todayCheckin = $this->resolveTodayCheckin($user);

        if (! $todayCheckin) {
            return [
                'assistant_message' => 'Sign in required setup: daily check-in is required before decision analysis.',
                'structured_json' => [
                    'error' => 'daily_checkin_required',
                ],
                'pending_action' => null,
            ];
        }

        $category = in_array(($extractedData['category'] ?? 'other'), Decision::CATEGORIES, true)
            ? (string) $extractedData['category']
            : 'other';

        $contextPayload = $this->normalizeDecisionContext($messageText, $extractedData);

        $validatedContext = Validator::make($contextPayload, [
            'what' => ['required', 'string', 'min:1'],
            'why' => ['required', 'string', 'min:1'],
            'when' => ['required', 'string', 'min:1'],
            'urgency' => ['required', Rule::in(['low', 'medium', 'high'])],
            'estimated_impact' => ['required', 'string', 'min:1'],
            'alternatives' => ['nullable', 'array'],
            'alternatives.*' => ['required', 'string', 'min:1'],
        ])->validate();

        $aiResult = $this->openRouterService->analyzeDecision(
            $doctrine,
            $todayCheckin,
            $category,
            $validatedContext
        );

        $decision = $user->decisions()->create([
            'category' => $category,
            'context_json' => $validatedContext,
            'ai_response_json' => $aiResult['structured'],
            'raw_ai_response' => $aiResult['raw'],
            'final_choice' => null,
            'outcome_notes' => null,
        ]);

        $verdict = $decision->ai_response_json['verdict'] ?? 'unknown';
        $confidence = $decision->ai_response_json['confidence'] ?? null;

        return [
            'assistant_message' => sprintf(
                'Decision review complete. Verdict: %s%s.',
                strtoupper((string) $verdict),
                is_int($confidence) ? " ({$confidence}% confidence)" : ''
            ),
            'structured_json' => [
                'decision_id' => $decision->id,
                'ai_response' => $decision->ai_response_json,
            ],
            'pending_action' => null,
        ];
    }

    private function resolveTodayCheckin(User $user): ?DailyCheckin
    {
        $timezone = (string) config('conversation.default_timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->toDateString();

        return $user->dailyCheckins()
            ->whereDate('checkin_date', $today)
            ->latest('created_at')
            ->first();
    }

    private function normalizeDecisionContext(string $messageText, array $extractedData): array
    {
        $candidate = $extractedData['context_json'] ?? $extractedData['context'] ?? $extractedData;
        $source = is_array($candidate) ? $candidate : [];

        $alternatives = $source['alternatives'] ?? [];
        if (! is_array($alternatives)) {
            $alternatives = [];
        }

        $alternatives = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $alternatives
        ), static fn (string $value): bool => $value !== ''));

        $urgency = strtolower(trim((string) ($source['urgency'] ?? 'medium')));
        if (! in_array($urgency, ['low', 'medium', 'high'], true)) {
            $urgency = 'medium';
        }

        return [
            'what' => trim((string) ($source['what'] ?? $messageText)),
            'why' => trim((string) ($source['why'] ?? 'User requested guidance in chat.')),
            'when' => trim((string) ($source['when'] ?? 'today')),
            'urgency' => $urgency,
            'estimated_impact' => trim((string) ($source['estimated_impact'] ?? 'Not specified')),
            'alternatives' => $alternatives,
        ];
    }
}
