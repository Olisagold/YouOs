<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramMessageJob;
use App\Models\TelegramLink;
use App\Services\ChatOrchestrator;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(
        Request $request,
        ChatOrchestrator $chatOrchestrator,
        TelegramService $telegramService
    ): JsonResponse {
        if (! $this->hasValidSecret($request)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Invalid Telegram secret token.',
            ], 403);
        }

        $chatId = trim((string) data_get($request->all(), 'message.chat.id', ''));
        $messageText = trim((string) data_get($request->all(), 'message.text', ''));

        if ($chatId === '' || $messageText === '') {
            return response()->json(['ok' => true]);
        }

        $linkedRecord = TelegramLink::query()
            ->where('code', $chatId)
            ->whereNotNull('used_at')
            ->first();

        if (! $linkedRecord) {
            $linkedRecord = $this->attemptLinkViaCode($chatId, $messageText, $telegramService);
        }

        if (! $linkedRecord) {
            $telegramService->sendMessage(
                $chatId,
                'Your Telegram is not linked yet. Use /link YOUR_CODE to connect your YouOS account.'
            );

            return response()->json(['ok' => true]);
        }

        if ((bool) config('conversation.llm_queue_enabled', false)) {
            ProcessTelegramMessageJob::dispatch(
                (int) $linkedRecord->user_id,
                $chatId,
                $messageText
            )->onQueue('llm');

            return response()->json([
                'ok' => true,
                'queued' => true,
            ], 202);
        }

        try {
            $result = $chatOrchestrator->handle(
                (int) $linkedRecord->user_id,
                'telegram',
                $chatId,
                $messageText,
                null
            );
        } catch (\Throwable $exception) {
            Log::error('telegram.webhook_orchestrator_failed', [
                'chat_id' => $chatId,
                'user_id' => $linkedRecord->user_id,
                'error' => $exception->getMessage(),
            ]);

            $telegramService->sendMessage(
                $chatId,
                'Temporary issue processing your message. Please try again.'
            );

            return response()->json(['ok' => true]);
        }

        $telegramService->sendMessage($chatId, $result['assistant_message']);

        return response()->json(['ok' => true]);
    }

    private function hasValidSecret(Request $request): bool
    {
        $expected = config('services.telegram.secret_token');

        if (! is_string($expected) || trim($expected) === '') {
            return false;
        }

        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token')
            ?? $request->header('TELEGRAM_SECRET_TOKEN');

        if (! is_string($provided) || trim($provided) === '') {
            return false;
        }

        return hash_equals(trim($expected), trim($provided));
    }

    private function attemptLinkViaCode(
        string $chatId,
        string $messageText,
        TelegramService $telegramService
    ): ?TelegramLink {
        if (! preg_match('/^\/link\s+([A-Za-z0-9_-]+)$/', $messageText, $matches)) {
            return null;
        }

        $linkCode = $matches[1];

        $pendingLink = TelegramLink::query()
            ->where('code', $linkCode)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $pendingLink) {
            $telegramService->sendMessage($chatId, 'Invalid or expired linking code.');

            return null;
        }

        $pendingLink->code = $chatId;
        $pendingLink->used_at = now();
        $pendingLink->save();

        $telegramService->sendMessage($chatId, 'Telegram linked successfully. You can chat with YouOS now.');

        return $pendingLink;
    }
}
