<?php

namespace App\Services;

use App\Support\ApiError;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntentExtractorService
{
    public const INTENT_DECISION = 'decision';
    public const INTENT_DOCTRINE_UPDATE = 'doctrine_update';
    public const INTENT_CHECKIN_START = 'checkin_start';
    public const INTENT_CHECKIN_REFLECTION = 'checkin_reflection';
    public const INTENT_REMINDER_CREATE = 'reminder_create';
    public const INTENT_GENERAL = 'general';

    private const ALLOWED_INTENTS = [
        self::INTENT_DECISION,
        self::INTENT_DOCTRINE_UPDATE,
        self::INTENT_CHECKIN_START,
        self::INTENT_CHECKIN_REFLECTION,
        self::INTENT_REMINDER_CREATE,
        self::INTENT_GENERAL,
    ];

    public function __construct(
        private readonly OpenRouterService $openRouterService
    ) {}

    public function extract(string $messageText, array $context, string $requestId): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($messageText, $context);

        $response = $this->openRouterService->requestChatCompletion(
            $systemPrompt,
            $userPrompt,
            0.1,
            500
        );

        $this->logRawResponse($requestId, 1, $response['raw']);

        try {
            $decoded = $this->decodeJson($response['content']);

            return $this->validatePayload($decoded);
        } catch (ValidationException $firstException) {
            $repairPrompt = $this->buildRepairPrompt($response['content'], $firstException->errors());
            $repairResponse = $this->openRouterService->requestChatCompletion(
                $systemPrompt,
                $repairPrompt,
                0.1,
                500
            );

            $this->logRawResponse($requestId, 2, $repairResponse['raw']);

            try {
                $decoded = $this->decodeJson($repairResponse['content']);

                return $this->validatePayload($decoded);
            } catch (ValidationException) {
                throw $this->invalidIntentException(
                    'AI response could not be validated as an intent payload after repair.'
                );
            }
        }
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You are an intent extractor for YouOS.',
            'Return JSON only. No markdown, no commentary, no code fences.',
            'Output schema:',
            '{',
            '  "intent": "decision|doctrine_update|checkin_start|checkin_reflection|reminder_create|general",',
            '  "confidence": 0,',
            '  "extracted_data": {}',
            '}',
            'Requirements:',
            '- confidence must be an integer in the range 0-100.',
            '- extracted_data must be an object.',
            '- intent must be one of the allowed values.',
            '- If information is missing, keep extracted_data sparse and set intent=general when uncertain.',
        ]);
    }

    private function buildUserPrompt(string $messageText, array $context): string
    {
        return implode("\n", [
            'Classify this user message and extract structured data.',
            'User message:',
            $messageText,
            'Context JSON:',
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function buildRepairPrompt(string $invalidResponse, array $errors): string
    {
        return implode("\n", [
            'Your previous output was invalid.',
            'Return ONLY valid JSON matching this exact schema:',
            '{"intent":"decision|doctrine_update|checkin_start|checkin_reflection|reminder_create|general","confidence":0,"extracted_data":{}}',
            'Validation errors:',
            json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Previous invalid output:',
            $invalidResponse,
        ]);
    }

    private function decodeJson(string $content): array
    {
        $normalized = trim($content);

        if (str_starts_with($normalized, '```')) {
            $normalized = preg_replace('/^```(?:json)?\s*/', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/\s*```$/', '', $normalized) ?? $normalized;
        }

        $decoded = json_decode($normalized, true);

        if (! is_array($decoded)) {
            throw $this->invalidIntentException('AI intent response is not valid JSON.');
        }

        return $decoded;
    }

    private function validatePayload(array $payload): array
    {
        $requiredKeys = ['intent', 'confidence', 'extracted_data'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                throw $this->invalidIntentException('AI intent response is missing required keys.');
            }
        }

        $validated = Validator::make($payload, [
            'intent' => ['required', 'string', Rule::in(self::ALLOWED_INTENTS)],
            'confidence' => ['required', 'integer', 'between:0,100'],
            'extracted_data' => ['required', 'array'],
        ])->validate();

        return [
            'intent' => $validated['intent'],
            'confidence' => $validated['confidence'],
            'extracted_data' => $validated['extracted_data'],
        ];
    }

    private function logRawResponse(string $requestId, int $attempt, string $rawResponse): void
    {
        Log::info('intent_extractor.raw_response', [
            'request_id' => $requestId,
            'attempt' => $attempt,
            'raw_response' => $rawResponse,
        ]);
    }

    private function invalidIntentException(string $message): ValidationException
    {
        $exception = ValidationException::withMessages([
            'ai_response' => [$message],
        ]);

        $exception->errorBag = ApiError::AI_RESPONSE_INVALID;

        return $exception;
    }
}
