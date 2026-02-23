<?php

namespace App\Services\Chat\Contracts;

use App\Models\Conversation;
use App\Models\User;

interface ChatHandlerInterface
{
    /**
     * @return array{
     *   assistant_message: string,
     *   structured_json?: array|null,
     *   pending_action?: \App\Models\PendingAction|null
     * }
     */
    public function handle(
        User $user,
        Conversation $conversation,
        string $messageText,
        array $extractedData,
        array $context,
        string $requestId
    ): array;
}
