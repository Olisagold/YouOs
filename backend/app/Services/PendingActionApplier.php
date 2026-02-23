<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\Decision;
use App\Models\DisciplineLog;
use App\Models\Doctrine;
use App\Models\PendingAction;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PendingActionApplier
{
    /**
     * @return array{assistant_message: string, structured_json: array}
     */
    public function apply(PendingAction $pendingAction): array
    {
        return match ($pendingAction->action_type) {
            'doctrine_update' => $this->applyDoctrineUpdate($pendingAction),
            'checkin_create' => $this->applyCheckinCreate($pendingAction),
            'reflection_log' => $this->applyReflectionLog($pendingAction),
            'reminder_create' => $this->applyReminderCreate($pendingAction),
            default => throw ValidationException::withMessages([
                'pending_action' => ['Unsupported pending action type.'],
            ]),
        };
    }

    private function applyDoctrineUpdate(PendingAction $pendingAction): array
    {
        $payload = $this->validateDoctrinePayload($pendingAction->payload_json ?? []);

        Doctrine::updateOrCreate(
            ['user_id' => $pendingAction->user_id],
            $payload
        );

        return [
            'assistant_message' => 'Doctrine updated successfully.',
            'structured_json' => [
                'action_type' => 'doctrine_update',
            ],
        ];
    }

    private function applyCheckinCreate(PendingAction $pendingAction): array
    {
        $payload = Validator::make($pendingAction->payload_json ?? [], [
            'energy' => ['required', 'integer', 'between:1,10'],
            'mood' => ['required', 'integer', 'between:1,10'],
            'missions_json' => ['required', 'array', 'min:1', 'max:3'],
            'missions_json.*' => ['required', 'string', 'min:1'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $timezone = (string) config('conversation.default_timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->toDateString();

        $exists = DailyCheckin::query()
            ->where('user_id', $pendingAction->user_id)
            ->whereDate('checkin_date', $today)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'checkin' => ['A daily check-in already exists for today.'],
            ]);
        }

        DailyCheckin::create([
            'user_id' => $pendingAction->user_id,
            'checkin_date' => $today,
            'energy' => $payload['energy'],
            'mood' => $payload['mood'],
            'missions_json' => $payload['missions_json'],
            'notes' => $payload['notes'] ?? null,
        ]);

        return [
            'assistant_message' => 'Check-in saved for today.',
            'structured_json' => [
                'action_type' => 'checkin_create',
            ],
        ];
    }

    private function applyReflectionLog(PendingAction $pendingAction): array
    {
        $payload = Validator::make($pendingAction->payload_json ?? [], [
            'completed' => ['required', 'boolean'],
            'violations' => ['required', 'array'],
            'violations.*' => ['required', 'string', 'min:1'],
            'notes' => ['nullable', 'string'],
            'decision_id' => ['nullable', 'integer'],
        ])->validate();

        $decisionId = $payload['decision_id'] ?? null;

        if ($decisionId !== null) {
            $decision = Decision::query()->find($decisionId);

            if (! $decision || (int) $decision->user_id !== (int) $pendingAction->user_id) {
                throw ValidationException::withMessages([
                    'decision_id' => ['Decision does not belong to this user.'],
                ]);
            }
        }

        $createdCount = 0;

        if ($payload['completed']) {
            DisciplineLog::create([
                'user_id' => $pendingAction->user_id,
                'decision_id' => $decisionId,
                'log_type' => 'complied',
                'reason' => $payload['notes'] ?? 'Completed missions.',
            ]);
            $createdCount++;
        }

        foreach ($payload['violations'] as $violation) {
            DisciplineLog::create([
                'user_id' => $pendingAction->user_id,
                'decision_id' => $decisionId,
                'log_type' => 'violation',
                'reason' => $violation,
            ]);
            $createdCount++;
        }

        if (! $payload['completed'] && $payload['violations'] === []) {
            DisciplineLog::create([
                'user_id' => $pendingAction->user_id,
                'decision_id' => $decisionId,
                'log_type' => 'skipped',
                'reason' => $payload['notes'] ?? 'Reflection logged without mission completion.',
            ]);
            $createdCount++;
        }

        return [
            'assistant_message' => 'Reflection logged successfully.',
            'structured_json' => [
                'action_type' => 'reflection_log',
                'logs_created' => $createdCount,
            ],
        ];
    }

    private function applyReminderCreate(PendingAction $pendingAction): array
    {
        $payload = Validator::make($pendingAction->payload_json ?? [], [
            'channel' => ['required', 'string', Rule::in(['telegram'])],
            'message' => ['required', 'string', 'min:1'],
            'scheduled_for' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
        ])->validate();

        $scheduledFor = CarbonImmutable::parse($payload['scheduled_for'])->setTimezone('UTC');

        $reminder = Reminder::create([
            'user_id' => $pendingAction->user_id,
            'channel' => $payload['channel'],
            'message' => $payload['message'],
            'scheduled_for' => $scheduledFor,
            'status' => Reminder::STATUS_PENDING,
        ]);

        return [
            'assistant_message' => 'Reminder created successfully.',
            'structured_json' => [
                'action_type' => 'reminder_create',
                'reminder_id' => $reminder->id,
            ],
        ];
    }

    private function validateDoctrinePayload(array $payload): array
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
