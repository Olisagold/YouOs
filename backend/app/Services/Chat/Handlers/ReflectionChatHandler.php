<?php

namespace App\Services\Chat\Handlers;

use App\Models\Conversation;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReflectionChatHandler implements ChatHandlerInterface
{
    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array {
        $violations = $this->normalizeStringList(
            $extractedData['violations']
                ?? $extractedData['violations_json']
                ?? []
        );

        $completed = filter_var($extractedData['completed'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($completed === null) {
            $completed = false;
        }

        $payload = [
            'completed' => $completed,
            'violations' => $violations,
            'notes' => isset($extractedData['notes']) ? trim((string) $extractedData['notes']) : null,
            'decision_id' => isset($extractedData['decision_id']) && is_numeric($extractedData['decision_id'])
                ? (int) $extractedData['decision_id']
                : null,
        ];

        try {
            $validated = Validator::make($payload, [
                'completed' => ['required', 'boolean'],
                'violations' => ['required', 'array'],
                'violations.*' => ['required', 'string', 'min:1'],
                'notes' => ['nullable', 'string'],
                'decision_id' => ['nullable', 'integer'],
            ])->validate();
        } catch (ValidationException) {
            return [
                'assistant_message' => 'I need a clearer reflection payload. Include whether you completed missions and any violations.',
                'structured_json' => [
                    'action' => 'reflection_log',
                    'status' => 'invalid_payload',
                ],
                'pending_action' => null,
            ];
        }

        if (! $validated['completed'] && $validated['violations'] === [] && blank($validated['notes'])) {
            return [
                'assistant_message' => 'Reflection is too empty to log. Tell me what was completed or what broke discipline.',
                'structured_json' => [
                    'action' => 'reflection_log',
                    'status' => 'insufficient_data',
                ],
                'pending_action' => null,
            ];
        }

        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'action_type' => 'reflection_log',
            'payload_json' => $validated,
            'expires_at' => CarbonImmutable::now()->addMinutes(
                max(1, (int) config('conversation.pending_action_ttl_minutes', 30))
            ),
        ]);

        return [
            'assistant_message' => 'Reflection draft ready. Reply "yes" to log it or "no" to cancel.',
            'structured_json' => [
                'action' => 'reflection_log',
                'preview' => $validated,
            ],
            'pending_action' => $pendingAction,
        ];
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
}
