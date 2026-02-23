<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendEndOfDayReflection extends Command
{
    protected $signature = 'youos:send-end-of-day-reflection';

    protected $description = 'Send end-of-day reflection prompt to users with a check-in today.';

    public function handle(TelegramService $telegramService): int
    {
        $timezone = (string) config('conversation.default_timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $prompt = 'Report: Did you complete your missions today?';
        $sent = 0;

        User::query()
            ->whereHas('dailyCheckins', function ($query) use ($today): void {
                $query->whereDate('checkin_date', $today);
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($telegramService, $prompt, &$sent): void {
                foreach ($users as $user) {
                    $chatId = $telegramService->resolveLinkedChatId((int) $user->id);

                    if (! $chatId) {
                        continue;
                    }

                    if ($telegramService->sendMessage($chatId, $prompt)) {
                        $sent++;
                    }
                }
            });

        $this->info(sprintf('End-of-day prompts sent: %d', $sent));

        return self::SUCCESS;
    }
}
