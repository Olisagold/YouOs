<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'youos:send-due-reminders';

    protected $description = 'Send pending reminders that are due.';

    public function handle(TelegramService $telegramService): int
    {
        $now = now()->utc();
        $sent = 0;
        $failed = 0;

        Reminder::query()
            ->where('status', Reminder::STATUS_PENDING)
            ->where('scheduled_for', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($reminders) use ($telegramService, &$sent, &$failed): void {
                foreach ($reminders as $reminder) {
                    if ($reminder->channel !== 'telegram') {
                        $reminder->status = Reminder::STATUS_FAILED;
                        $reminder->save();
                        $failed++;
                        continue;
                    }

                    $chatId = $telegramService->resolveLinkedChatId((int) $reminder->user_id);

                    if (! $chatId) {
                        $reminder->status = Reminder::STATUS_FAILED;
                        $reminder->save();
                        $failed++;
                        continue;
                    }

                    $delivered = $telegramService->sendMessage($chatId, $reminder->message);

                    $reminder->status = $delivered ? Reminder::STATUS_SENT : Reminder::STATUS_FAILED;
                    $reminder->save();

                    if ($delivered) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }
            });

        $this->info(sprintf('Reminders processed. sent=%d failed=%d', $sent, $failed));

        return self::SUCCESS;
    }
}
