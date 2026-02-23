<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OpenRouterUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ChatMessageRequest;
use App\Services\ChatOrchestrator;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function store(ChatMessageRequest $request, ChatOrchestrator $chatOrchestrator): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $chatOrchestrator->handle(
                (int) $request->user()->id,
                'web',
                null,
                $validated['message'],
                isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null
            );
        } catch (ValidationException $exception) {
            $errorCode = $exception->errorBag === ApiError::AI_RESPONSE_INVALID
                ? ApiError::AI_RESPONSE_INVALID
                : 'chat_validation_failed';

            $message = $errorCode === ApiError::AI_RESPONSE_INVALID
                ? 'AI output invalid, try again.'
                : 'Chat request validation failed.';

            return ApiError::response($errorCode, $message, 422, [
                'validation_errors' => $exception->errors(),
            ]);
        } catch (OpenRouterUnavailableException $exception) {
            return ApiError::response(
                ApiError::OPENROUTER_UNAVAILABLE,
                $exception->getMessage(),
                503,
                ['status' => $exception->statusCode()]
            );
        }

        return response()->json($result);
    }
}
