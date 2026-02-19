<?php

namespace App\AI\Prompts;

use App\Models\DailyCheckin;
use App\Models\Doctrine;

class DecisionPromptBuilder
{
    /**
     * @return array{system: string, user: string}
     */
    public function build(
        Doctrine $doctrine,
        DailyCheckin $dailyCheckin,
        string $category,
        array $decisionContext
    ): array {
        $inputPayload = [
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

        $schema = [
            'verdict' => 'approve | reject | delay',
            'confidence' => 'integer 0-100',
            'reasoning' => ['string', '...'],
            'risks' => ['string', '...'],
            'better_option' => 'string',
            'next_steps' => ['string', '...'],
        ];

        $systemMessage = implode(' ', [
            'You are a stoic disciplined decision coach.',
            'Use doctrine-first reasoning and objective analysis only.',
            'Return ONLY raw JSON, no markdown, no backticks.',
        ]);

        $userMessage = implode("\n", [
            'Evaluate the decision against doctrine and today state.',
            'Required output JSON schema (exact keys, no extras):',
            json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'Rules:',
            '- verdict must be one of approve, reject, delay',
            '- confidence must be integer between 0 and 100',
            '- reasoning must be array of 2 to 6 strings',
            '- risks must be array of strings (can be empty)',
            '- better_option must be a string (empty string allowed only if verdict is approve)',
            '- next_steps must be an array of actionable strings',
            'Input payload:',
            json_encode($inputPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        return [
            'system' => $systemMessage,
            'user' => $userMessage,
        ];
    }
}
