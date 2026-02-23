<?php

namespace App\Services\Chat\Handlers;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\Contracts\ChatHandlerInterface;

class GeneralChatHandler implements ChatHandlerInterface
{
    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array {
        return [
            'assistant_message' => 'Hold to what is in your control, act with discipline, and judge the day by your choices, not by noise.',
            'structured_json' => [
                'intent' => 'general',
            ],
            'pending_action' => null,
        ];
    }
}
