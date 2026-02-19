<?php

namespace App\Services;

use App\AI\Prompts\DecisionPromptBuilder;
use App\Exceptions\OpenRouterUnavailableException;
use App\Models\DailyCheckin;
use App\Models\Doctrine;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OpenRouterService
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'meta-llama/llama-3.3-70b-instruct:free';

    public function __construct(
        private readonly DecisionPromptBuilder $decisionPromptBuilder
    ) {}

    public function analyzeDecision(
        Doctrine $doctrine,
        DailyCheckin $dailyCheckin,
        string $category,
        array $decisionContext
    ): array {
        $messages = $this->decisionPromptBuilder->build(
            $doctrine,
            $dailyCheckin,
            $category,
            $decisionContext
        );

        $chatResponse = $this->requestChatCompletion(
            $messages['system'],
            $messages['user']
        );

        $rawResponse = $chatResponse['raw'];
        $content = $chatResponse['content'];

        if (! is_string($content) || trim($content) === '') {
            throw ValidationException::withMessages([
                'ai_response' => ['AI response content is missing or invalid.'],
            ]);
        }

        $structured = $this->validateAiResponse($this->decodeJson($content));

        return [
            'structured' => $structured,
            'raw' => $rawResponse,
        ];
    }

    /**
     * @return array{raw: string, content: string}
     */
    public function requestChatCompletion(
        string $systemMessage,
        string $userMessage,
        float $temperature = 0.2,
        int $maxTokens = 800
    ): array {
        $apiKey = config('services.openrouter.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new OpenRouterUnavailableException('OPENROUTER_API_KEY is missing.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(self::ENDPOINT, [
                'model' => self::MODEL,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemMessage,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw new OpenRouterUnavailableException(
                'OpenRouter request failed due to a connection error.'
            );
        }

        if (! $response->successful()) {
            throw new OpenRouterUnavailableException(
                'OpenRouter returned a non-success status code.',
                $response->status(),
                $response->body()
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw ValidationException::withMessages([
                'ai_response' => ['AI response content is missing or invalid.'],
            ]);
        }

        return [
            'raw' => $response->body(),
            'content' => $content,
        ];
    }

    public function decodeJsonContent(string $content): array
    {
        return $this->decodeJson($content);
    }

    private function decodeJson(string $content): array
    {
        $normalized = trim($content);

        $decoded = json_decode($normalized, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'ai_response' => ['AI response is not valid JSON.'],
            ]);
        }

        return $decoded;
    }

    private function validateAiResponse(array $payload): array
    {
        $expectedKeys = [
            'verdict',
            'confidence',
            'reasoning',
            'risks',
            'better_option',
            'next_steps',
        ];

        $actualKeys = array_keys($payload);
        sort($expectedKeys);
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys) {
            throw ValidationException::withMessages([
                'ai_response' => ['AI response must contain exactly the expected schema keys.'],
            ]);
        }

        return Validator::make($payload, [
            'verdict' => ['required', 'string', Rule::in(['approve', 'reject', 'delay'])],
            'confidence' => ['required', 'integer', 'between:0,100'],
            'reasoning' => ['required', 'array', 'min:2', 'max:6'],
            'reasoning.*' => ['required', 'string', 'min:1'],
            'risks' => ['required', 'array'],
            'risks.*' => ['required', 'string', 'min:1'],
            'better_option' => ['present', 'string'],
            'next_steps' => ['required', 'array', 'min:1'],
            'next_steps.*' => ['required', 'string', 'min:1'],
        ])->after(function ($validator) use ($payload): void {
            if ($payload['verdict'] === 'approve' && $payload['better_option'] !== '') {
                $validator->errors()->add(
                    'better_option',
                    'better_option must be empty when verdict is approve.'
                );
            }

            if ($payload['verdict'] !== 'approve' && trim($payload['better_option']) === '') {
                $validator->errors()->add(
                    'better_option',
                    'better_option is required when verdict is not approve.'
                );
            }
        })->validate();
    }
}
