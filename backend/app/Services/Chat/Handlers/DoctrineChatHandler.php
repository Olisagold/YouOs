<?php

namespace App\Services\Chat\Handlers;

use App\Models\Conversation;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DoctrineChatHandler implements ChatHandlerInterface
{
    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array {
        $existingDoctrine = $user->doctrine()->first();

        $payload = [
            'goals_json' => $this->normalizeGoals(
                $extractedData['goals_json']
                    ?? $extractedData['goals']
                    ?? $existingDoctrine?->goals_json
                    ?? []
            ),
            'rules_json' => $this->normalizeStringList(
                $extractedData['rules_json']
                    ?? $extractedData['rules']
                    ?? $existingDoctrine?->rules_json
                    ?? []
            ),
            'habits_json' => $this->normalizeHabits(
                $extractedData['habits_json']
                    ?? $extractedData['habits']
                    ?? $existingDoctrine?->habits_json
                    ?? []
            ),
            'weekly_targets_json' => $this->normalizeWeeklyTargets(
                $extractedData['weekly_targets_json']
                    ?? $extractedData['weekly_targets']
                    ?? $existingDoctrine?->weekly_targets_json
                    ?? []
            ),
        ];

        if (
            ! $existingDoctrine
            && (
                $payload['goals_json'] === []
                || $payload['rules_json'] === []
                || $payload['habits_json'] === []
                || $payload['weekly_targets_json'] === []
            )
        ) {
            return [
                'assistant_message' => 'To create your doctrine, include goals, rules, habits, and weekly targets in your message.',
                'structured_json' => [
                    'action' => 'doctrine_update',
                    'status' => 'missing_required_fields',
                ],
                'pending_action' => null,
            ];
        }

        try {
            $validated = $this->validatePayload($payload);
        } catch (ValidationException) {
            return [
                'assistant_message' => 'I can draft the doctrine update, but the extracted structure is incomplete. Please include clearer goals, rules, habits, and weekly targets.',
                'structured_json' => [
                    'action' => 'doctrine_update',
                    'status' => 'invalid_payload',
                ],
                'pending_action' => null,
            ];
        }

        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'action_type' => 'doctrine_update',
            'payload_json' => $validated,
            'expires_at' => CarbonImmutable::now()->addMinutes(
                max(1, (int) config('conversation.pending_action_ttl_minutes', 30))
            ),
        ]);

        return [
            'assistant_message' => sprintf(
                'Proposed doctrine update: %d goals, %d rules, %d habits, %d weekly targets. Reply "yes" to confirm or "no" to cancel.',
                count($validated['goals_json']),
                count($validated['rules_json']),
                count($validated['habits_json']),
                count($validated['weekly_targets_json']),
            ),
            'structured_json' => [
                'action' => 'doctrine_update',
                'preview' => $validated,
            ],
            'pending_action' => $pendingAction,
        ];
    }

    private function normalizeGoals(mixed $goals): array
    {
        if (! is_array($goals)) {
            return [];
        }

        $normalized = [];

        foreach ($goals as $index => $goal) {
            if (is_string($goal) && trim($goal) !== '') {
                $normalized[] = [
                    'rank' => $index + 1,
                    'goal' => trim($goal),
                ];
                continue;
            }

            if (is_array($goal)) {
                $rank = isset($goal['rank']) && is_numeric($goal['rank']) ? (int) $goal['rank'] : ($index + 1);
                $goalText = trim((string) ($goal['goal'] ?? ''));
                if ($goalText !== '') {
                    $normalized[] = [
                        'rank' => $rank,
                        'goal' => $goalText,
                    ];
                }
            }
        }

        return $normalized;
    }

    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => $value !== ''));
    }

    private function normalizeHabits(mixed $habits): array
    {
        if (! is_array($habits)) {
            return [];
        }

        $normalized = [];

        foreach ($habits as $habit) {
            if (is_string($habit) && trim($habit) !== '') {
                $normalized[] = [
                    'habit' => trim($habit),
                    'trigger' => 'Context trigger required',
                ];
                continue;
            }

            if (is_array($habit)) {
                $habitText = trim((string) ($habit['habit'] ?? ''));
                $triggerText = trim((string) ($habit['trigger'] ?? ''));

                if ($habitText !== '' && $triggerText !== '') {
                    $normalized[] = [
                        'habit' => $habitText,
                        'trigger' => $triggerText,
                    ];
                }
            }
        }

        return $normalized;
    }

    private function normalizeWeeklyTargets(mixed $targets): array
    {
        if (! is_array($targets)) {
            return [];
        }

        $normalized = [];

        foreach ($targets as $target) {
            if (! is_array($target)) {
                continue;
            }

            $targetText = trim((string) ($target['target'] ?? ''));
            $metric = trim((string) ($target['metric'] ?? ''));
            $current = $target['current'] ?? null;
            $goal = $target['goal'] ?? null;

            if (
                $targetText !== ''
                && $metric !== ''
                && is_numeric($current)
                && is_numeric($goal)
            ) {
                $normalized[] = [
                    'target' => $targetText,
                    'metric' => $metric,
                    'current' => (float) $current,
                    'goal' => (float) $goal,
                ];
            }
        }

        return $normalized;
    }

    private function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'goals_json' => ['required', 'array', 'min:1'],
            'goals_json.*' => ['required', 'array'],
            'goals_json.*.rank' => ['required', 'integer', 'distinct'],
            'goals_json.*.goal' => ['required', 'string', 'min:1'],

            'rules_json' => ['required', 'array', 'min:1'],
            'rules_json.*' => ['required', 'string', 'min:1'],

            'habits_json' => ['required', 'array', 'min:1'],
            'habits_json.*' => ['required', 'array'],
            'habits_json.*.habit' => ['required', 'string', 'min:1'],
            'habits_json.*.trigger' => ['required', 'string', 'min:1'],

            'weekly_targets_json' => ['required', 'array', 'min:1'],
            'weekly_targets_json.*' => ['required', 'array'],
            'weekly_targets_json.*.target' => ['required', 'string', 'min:1'],
            'weekly_targets_json.*.metric' => ['required', 'string', 'min:1'],
            'weekly_targets_json.*.current' => ['required', 'numeric'],
            'weekly_targets_json.*.goal' => ['required', 'numeric'],
        ])->validate();
    }
}
