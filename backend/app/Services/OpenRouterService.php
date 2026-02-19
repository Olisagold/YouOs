<?php

namespace App\Services;

use App\Exceptions\OpenRouterUnavailableException;
use App\Models\DailyCheckin;
use App\Models\Doctrine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OpenRouterService
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'meta-llama/llama-3.3-70b-instruct:free';

    public function analyzeDecision(
        Doctrine $doctrine,
        DailyCheckin $dailyCheckin,
        string $category,
        array $decisionContext
    ): array {
        $apiKey = config('services.openrouter.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new OpenRouterUnavailableException('OPENROUTER_API_KEY is missing.');
        }

        $prompt = $this->buildPrompt($doctrine, $dailyCheckin, $category, $decisionContext);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::ENDPOINT, [
            'model' => self::MODEL,
            'temperature' => 0.2,
            'max_tokens' => 800,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a stoic, disciplined coach. Reply with JSON only.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new OpenRouterUnavailableException(
                'OpenRouter returned a non-success status code.',
                $response->status(),
                $response->body()
            );
        }

        $rawResponse = $response->body();
        $content = $response->json('choices.0.message.content');

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

    public function buildPrompt(
        Doctrine $doctrine,
        DailyCheckin $dailyCheckin,
        string $category,
        array $decisionContext
    ): string {
        $payload = [
            'doctrine' => [
                'goals' => $doctrine->goals_json,
                'rules' => $doctrine->rules_json,
                'habits' => $doctrine->habits_json,
                'weekly_targets' => $doctrine->weekly_targets_json,
            ],
            'today_checkin' => [
                'energy' => $dailyCheckin->energy,
                'mood' => $dailyCheckin->mood,
                'missions' => $dailyCheckin->missions_json,
                'notes' => $dailyCheckin->notes,
            ],
            'decision' => [
                'category' => $category,
                'context' => $decisionContext,
            ],
        ];

        return implode("\n", [
            'Evaluate this decision using the provided doctrine and today check-in.',
            'Return ONLY one JSON object with exactly these keys:',
            '{"verdict":"approve|reject|delay","confidence":0-100,"reasoning":[...],"risks":[...],"better_option":"...","next_steps":[...]}',
            'Do not include markdown, code fences, or additional keys.',
            'Input:',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);
    }

    private function decodeJson(string $content): array
    {
        $normalized = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $normalized, $matches) === 1) {
            $normalized = trim($matches[1]);
        }

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
            'reasoning' => ['required', 'array', 'min:1'],
            'reasoning.*' => ['required', 'string', 'min:1'],
            'risks' => ['required', 'array'],
            'risks.*' => ['required', 'string', 'min:1'],
            'better_option' => ['required', 'string', 'min:1'],
            'next_steps' => ['required', 'array', 'min:1'],
            'next_steps.*' => ['required', 'string', 'min:1'],
        ])->validate();
    }
}
