<?php

namespace App\Jobs;

use App\Services\ChatOrchestrator;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $userId,
        private readonly string $chatId,
        private readonly string $messageText
    ) {}

    public function handle(ChatOrchestrator $chatOrchestrator, TelegramService $telegramService): void
    {
        try {
            $result = $chatOrchestrator->handle(
                $this->userId,
                'telegram',
                $this->chatId,
                $this->messageText,
                null
            );

            $telegramService->sendMessage($this->chatId, $result['assistant_message']);
        } catch (\Throwable $exception) {
            Log::error('telegram.process_job_failed', [
                'user_id' => $this->userId,
                'chat_id' => $this->chatId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
