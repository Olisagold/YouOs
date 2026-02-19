<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    public const DAILY_CHECKIN_REQUIRED = 'daily_checkin_required';
    public const DOCTRINE_REQUIRED = 'doctrine_required';
    public const AI_RESPONSE_INVALID = 'ai_response_invalid';
    public const OPENROUTER_UNAVAILABLE = 'openrouter_unavailable';

    public static function response(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
