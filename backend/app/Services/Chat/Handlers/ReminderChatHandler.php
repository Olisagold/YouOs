<?php

namespace App\Services\Chat\Handlers;

use App\Models\Conversation;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReminderChatHandler implements ChatHandlerInterface
{
    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array {
        $timezone = $this->resolveTimezone($extractedData['timezone'] ?? null);
        $scheduledFor = $this->normalizeScheduledFor(
            $extractedData['scheduled_for'] ?? $extractedData['time'] ?? null,
            $timezone
        );

        if (! $scheduledFor) {
            return [
                'assistant_message' => 'I need a valid reminder time. Example: "tomorrow 8:00 AM".',
                'structured_json' => [
                    'action' => 'reminder_create',
                    'status' => 'invalid_time',
                ],
                'pending_action' => null,
            ];
        }

        $payload = [
            'channel' => 'telegram',
            'message' => trim((string) ($extractedData['message'] ?? $extractedData['text'] ?? '')),
            'scheduled_for' => $scheduledFor->toIso8601String(),
            'timezone' => $timezone,
        ];

        if ($payload['message'] === '') {
            return [
                'assistant_message' => 'I need reminder text. Example: "Remind me to review doctrine".',
                'structured_json' => [
                    'action' => 'reminder_create',
                    'status' => 'missing_message',
                ],
                'pending_action' => null,
            ];
        }

        try {
            $validated = Validator::make($payload, [
                'channel' => ['required', 'string'],
                'message' => ['required', 'string', 'min:1'],
                'scheduled_for' => ['required', 'date'],
                'timezone' => ['required', 'timezone'],
            ])->validate();
        } catch (ValidationException) {
            return [
                'assistant_message' => 'Reminder details are invalid. Please provide a clearer time and message.',
                'structured_json' => [
                    'action' => 'reminder_create',
                    'status' => 'invalid_payload',
                ],
                'pending_action' => null,
            ];
        }

        $pendingAction = PendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'action_type' => 'reminder_create',
            'payload_json' => $validated,
            'expires_at' => CarbonImmutable::now()->addMinutes(
                max(1, (int) config('conversation.pending_action_ttl_minutes', 30))
            ),
        ]);

        return [
            'assistant_message' => sprintf(
                'Reminder draft: "%s" at %s (%s). Reply "yes" to confirm or "no" to cancel.',
                $validated['message'],
                CarbonImmutable::parse($validated['scheduled_for'])->setTimezone($timezone)->format('Y-m-d H:i'),
                $timezone
            ),
            'structured_json' => [
                'action' => 'reminder_create',
                'preview' => $validated,
            ],
            'pending_action' => $pendingAction,
        ];
    }

    private function resolveTimezone(mixed $timezone): string
    {
        $candidate = is_string($timezone) ? trim($timezone) : '';

        if ($candidate !== '' && in_array($candidate, timezone_identifiers_list(), true)) {
            return $candidate;
        }

        return (string) config('conversation.default_timezone', 'UTC');
    }

    private function normalizeScheduledFor(mixed $rawValue, string $timezone): ?CarbonImmutable
    {
        if (! is_string($rawValue) || trim($rawValue) === '') {
            return null;
        }

        try {
            $parsedLocal = CarbonImmutable::parse($rawValue, $timezone);
        } catch (\Throwable) {
            return null;
        }

        if ($parsedLocal->lessThanOrEqualTo(CarbonImmutable::now($timezone))) {
            $parsedLocal = $parsedLocal->addDay();
        }

        return $parsedLocal->setTimezone('UTC');
    }
}
