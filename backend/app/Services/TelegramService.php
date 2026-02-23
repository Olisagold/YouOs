<?php

namespace App\Services;

use App\Models\TelegramLink;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $chatId, string $text): bool
    {
        $botToken = config('services.telegram.bot_token');

        if (! is_string($botToken) || trim($botToken) === '') {
            Log::warning('telegram.send.skipped_missing_bot_token');

            return false;
        }

        try {
            $response = Http::timeout(15)->post(
                sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken),
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]
            );
        } catch (ConnectionException $exception) {
            Log::warning('telegram.send.connection_error', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('telegram.send.failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function resolveLinkedChatId(int $userId): ?string
    {
        $link = TelegramLink::query()
            ->where('user_id', $userId)
            ->whereNotNull('used_at')
            ->latest('used_at')
            ->first();

        if (! $link || ! is_string($link->code) || trim($link->code) === '') {
            return null;
        }

        return trim($link->code);
    }
}
