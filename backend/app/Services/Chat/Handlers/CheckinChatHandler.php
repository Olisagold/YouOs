<?php

namespace App\Services\Chat\Handlers;

use App\Models\Conversation;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckinChatHandler implements ChatHandlerInterface
{
    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array {
        $missions = $this->normalizeMissions(
            $extractedData['missions_json'] ?? $extractedData['missions'] ?? []
        );

        $payload = [
            'energy' => isset($extractedData['energy']) && is_numeric($extractedData['energy'])
                ? (int) $extractedData['energy']
                : 7,
            'mood' => isset($extractedData['mood']) && is_numeric($extractedData['mood'])
                ? (int) $extractedData['mood']
                : 7,
            'missions_json' => $missions,
            'notes' => isset($extractedData['notes']) ? trim((string) $extractedData['notes']) : null,
        ];

        try {
            $validated = $this->validatePayload($payload);
        } catch (ValidationException) {
            return [
                'assistant_message' => 'I need 1 to 3 clear missions for today before I can create your check-in.',
                'structured_json' => [
                    'action' => 'checkin_create',
                    'status' => 'invalid_payload',
                ],
                'pending_action' => null,
            ];
        }

        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'action_type' => 'checkin_create',
            'payload_json' => $validated,
            'expires_at' => CarbonImmutable::now()->addMinutes(
                max(1, (int) config('conversation.pending_action_ttl_minutes', 30))
            ),
        ]);

        return [
            'assistant_message' => sprintf(
                'Check-in draft ready: energy %d/10, mood %d/10, missions: %s. Reply "yes" to confirm or "no" to cancel.',
                $validated['energy'],
                $validated['mood'],
                implode(', ', $validated['missions_json']),
            ),
            'structured_json' => [
                'action' => 'checkin_create',
                'preview' => $validated,
            ],
            'pending_action' => $pendingAction,
        ];
    }

    private function normalizeMissions(mixed $missions): array
    {
        if (! is_array($missions)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $missions
        ), static fn (string $value): bool => $value !== ''));
    }

    private function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'energy' => ['required', 'integer', 'between:1,10'],
            'mood' => ['required', 'integer', 'between:1,10'],
            'missions_json' => ['required', 'array', 'min:1', 'max:3'],
            'missions_json.*' => ['required', 'string', 'min:1'],
            'notes' => ['nullable', 'string'],
        ])->validate();
    }
}
