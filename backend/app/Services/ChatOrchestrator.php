<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;
use App\Services\Chat\Handlers\CheckinChatHandler;
use App\Services\Chat\Handlers\DecisionChatHandler;
use App\Services\Chat\Handlers\DoctrineChatHandler;
use App\Services\Chat\Handlers\GeneralChatHandler;
use App\Services\Chat\Handlers\ReflectionChatHandler;
use App\Services\Chat\Handlers\ReminderChatHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatOrchestrator
{
    public function __construct(
        private readonly IntentExtractorService $intentExtractorService,
        private readonly PendingActionApplier $pendingActionApplier,
        private readonly DoctrineChatHandler $doctrineChatHandler,
        private readonly CheckinChatHandler $checkinChatHandler,
        private readonly DecisionChatHandler $decisionChatHandler,
        private readonly ReflectionChatHandler $reflectionChatHandler,
        private readonly ReminderChatHandler $reminderChatHandler,
        private readonly GeneralChatHandler $generalChatHandler
    ) {}

    /**
     * @return array{
     *   assistant_message: string,
     *   conversation_id: int,
     *   pending_action: array|null,
     *   request_id: string
     * }
     */
    public function handle(
        int $userId,
        string $channel,
        ?string $externalId,
        string $messageText,
        ?int $conversationId = null
    ): array {
        $requestId = (string) Str::uuid();
        $user = User::query()->findOrFail($userId);
        $conversation = $this->resolveConversation($user, $channel, $externalId, $conversationId);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $messageText,
            'structured_json' => null,
            'created_at' => now(),
        ]);

        $pendingResult = $this->handlePendingActionIfAny($user, $conversation, $messageText, $requestId);
        if ($pendingResult !== null) {
            return $pendingResult;
        }

        $context = $this->buildContext($user, $conversation);
        $intentPayload = $this->intentExtractorService->extract($messageText, $context, $requestId);
        $handler = $this->resolveHandler($intentPayload['intent']);

        $handlerResult = $handler->handle(
            $user,
            $conversation,
            $messageText,
            $intentPayload['extracted_data'],
            $context,
            $requestId
        );

        $structuredJson = [
            'request_id' => $requestId,
            'intent' => $intentPayload['intent'],
            'confidence' => $intentPayload['confidence'],
            'handler' => $handlerResult['structured_json'] ?? null,
        ];

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $handlerResult['assistant_message'],
            'structured_json' => $structuredJson,
            'created_at' => now(),
        ]);

        $pendingAction = $handlerResult['pending_action'] ?? null;

        Log::info('chat_orchestrator.completed', [
            'request_id' => $requestId,
            'user_id' => $user->id,
            'channel' => $channel,
            'conversation_id' => $conversation->id,
            'intent' => $intentPayload['intent'],
        ]);

        return [
            'assistant_message' => $handlerResult['assistant_message'],
            'conversation_id' => $conversation->id,
            'pending_action' => $pendingAction ? [
                'id' => $pendingAction->id,
                'action_type' => $pendingAction->action_type,
                'expires_at' => optional($pendingAction->expires_at)->toIso8601String(),
            ] : null,
            'request_id' => $requestId,
        ];
    }

    private function resolveConversation(
        User $user,
        string $channel,
        ?string $externalId,
        ?int $conversationId
    ): Conversation {
        if ($channel === 'telegram') {
            if (! is_string($externalId) || trim($externalId) === '') {
                throw ValidationException::withMessages([
                    'external_id' => ['external_id is required for telegram channel.'],
                ]);
            }

            return Conversation::query()->firstOrCreate([
                'user_id' => $user->id,
                'channel' => 'telegram',
                'external_id' => trim($externalId),
            ]);
        }

        if ($conversationId !== null) {
            $existing = Conversation::query()
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->where('channel', 'web')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Conversation::query()->create([
            'user_id' => $user->id,
            'channel' => 'web',
            'external_id' => null,
        ]);
    }

    /**
     * @return array{
     *   assistant_message: string,
     *   conversation_id: int,
     *   pending_action: array|null,
     *   request_id: string
     * }|null
     */
    private function handlePendingActionIfAny(
        User $user,
        Conversation $conversation,
        string $messageText,
        string $requestId
    ): ?array {
        $pendingAction = PendingAction::query()
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        if (! $pendingAction) {
            return null;
        }

        if ($pendingAction->expires_at && $pendingAction->expires_at->isPast()) {
            $pendingAction->delete();

            return null;
        }

        if ($this->isCancellation($messageText)) {
            $pendingAction->delete();
            $assistantMessage = 'Cancelled. No changes were applied.';

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $assistantMessage,
                'structured_json' => [
                    'request_id' => $requestId,
                    'pending_action' => 'cancelled',
                ],
                'created_at' => now(),
            ]);

            return [
                'assistant_message' => $assistantMessage,
                'conversation_id' => $conversation->id,
                'pending_action' => null,
                'request_id' => $requestId,
            ];
        }

        if (! $this->isConfirmation($messageText)) {
            return null;
        }

        $applyResult = DB::transaction(function () use ($pendingAction): array {
            $lockedAction = PendingAction::query()
                ->whereKey($pendingAction->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedAction) {
                return [
                    'assistant_message' => 'This confirmation was already processed.',
                    'structured_json' => [
                        'pending_action' => 'already_processed',
                    ],
                ];
            }

            if ($lockedAction->expires_at && $lockedAction->expires_at->isPast()) {
                $lockedAction->delete();

                return [
                    'assistant_message' => 'This confirmation request expired. Send the request again.',
                    'structured_json' => [
                        'pending_action' => 'expired',
                    ],
                ];
            }

            $result = $this->pendingActionApplier->apply($lockedAction);
            $lockedAction->delete();

            return $result;
        });

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $applyResult['assistant_message'],
            'structured_json' => [
                'request_id' => $requestId,
                'pending_action' => 'confirmed',
                'result' => $applyResult['structured_json'] ?? null,
            ],
            'created_at' => now(),
        ]);

        return [
            'assistant_message' => $applyResult['assistant_message'],
            'conversation_id' => $conversation->id,
            'pending_action' => null,
            'request_id' => $requestId,
        ];
    }

    private function isConfirmation(string $messageText): bool
    {
        return (bool) preg_match('/^(yes|y|confirm|confirmed|ok|okay|proceed|do it)$/i', trim($messageText));
    }

    private function isCancellation(string $messageText): bool
    {
        return (bool) preg_match('/^(no|n|cancel|stop|never mind)$/i', trim($messageText));
    }

    private function buildContext(User $user, Conversation $conversation): array
    {
        $messageLimit = max(1, (int) config('conversation.context_message_limit', 15));
        $charLimit = max(100, (int) config('conversation.context_char_limit', 1200));
        $timezone = (string) config('conversation.default_timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->toDateString();

        $doctrine = $user->doctrine()->first();
        $todayCheckin = $user->dailyCheckins()
            ->whereDate('checkin_date', $today)
            ->latest('created_at')
            ->first();

        $messages = $conversation->messages()
            ->latest('created_at')
            ->limit($messageLimit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => Str::limit((string) $message->content, $charLimit, ''),
                'created_at' => optional($message->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'doctrine_summary' => $doctrine ? [
                'goals' => array_slice($doctrine->goals_json ?? [], 0, 3),
                'rules' => array_slice($doctrine->rules_json ?? [], 0, 5),
                'habits' => array_slice($doctrine->habits_json ?? [], 0, 5),
                'weekly_targets' => array_slice($doctrine->weekly_targets_json ?? [], 0, 5),
            ] : null,
            'today_checkin_summary' => $todayCheckin ? [
                'energy' => $todayCheckin->energy,
                'mood' => $todayCheckin->mood,
                'missions' => $todayCheckin->missions_json,
            ] : null,
            'recent_messages' => $messages,
        ];
    }

    private function resolveHandler(string $intent): ChatHandlerInterface
    {
        return match ($intent) {
            IntentExtractorService::INTENT_DOCTRINE_UPDATE => $this->doctrineChatHandler,
            IntentExtractorService::INTENT_CHECKIN_START => $this->checkinChatHandler,
            IntentExtractorService::INTENT_DECISION => $this->decisionChatHandler,
            IntentExtractorService::INTENT_CHECKIN_REFLECTION => $this->reflectionChatHandler,
            IntentExtractorService::INTENT_REMINDER_CREATE => $this->reminderChatHandler,
            default => $this->generalChatHandler,
        };
    }
}
