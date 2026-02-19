<?php

namespace App\AI\Prompts;

use App\Models\Doctrine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WeeklyReviewPromptBuilder
{
    /**
     * @return array{system: string, user: string}
     */
    public function build(
        Doctrine $doctrine,
        Collection $dailyCheckins,
        Collection $decisions,
        Collection $disciplineLogs,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd
    ): array {
        $inputPayload = [
            'week_range' => [
                'start' => $weekStart->toIso8601String(),
                'end' => $weekEnd->toIso8601String(),
            ],
            'doctrine' => [
                'goals' => $doctrine->goals_json,
                'rules' => $doctrine->rules_json,
                'habits' => $doctrine->habits_json,
                'weekly_targets' => $doctrine->weekly_targets_json,
            ],
            'daily_checkins' => $dailyCheckins
                ->map(fn ($checkin): array => [
                    'checkin_date' => optional($checkin->checkin_date)->toDateString(),
                    'energy' => $checkin->energy,
                    'mood' => $checkin->mood,
                    'missions' => $checkin->missions_json,
                    'notes' => $checkin->notes,
                    'created_at' => optional($checkin->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'decisions' => $decisions
                ->map(fn ($decision): array => [
                    'category' => $decision->category,
                    'context' => $decision->context_json,
                    'ai_response' => $decision->ai_response_json,
                    'final_choice' => $decision->final_choice,
                    'outcome_notes' => $decision->outcome_notes,
                    'created_at' => optional($decision->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'discipline_logs' => $disciplineLogs
                ->map(fn ($log): array => [
                    'decision_id' => $log->decision_id,
                    'log_type' => $log->log_type,
                    'reason' => $log->reason,
                    'created_at' => optional($log->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];

        $schema = [
            'week_summary' => 'string',
            'compliance_rate' => 'number between 0.0 and 1.0',
            'patterns_detected' => ['string', '...'],
            'strongest_day' => 'string',
            'weakest_day' => 'string',
            'override_analysis' => 'string',
            'directive' => 'string',
            'doctrine_alignment_score' => 'integer 0-100',
        ];

        $systemMessage = implode(' ', [
            'You are a stoic disciplined weekly review engine.',
            'Use only doctrine-aligned, objective analysis grounded in provided weekly data.',
            'Return ONLY raw JSON, no markdown, no backticks.',
        ]);

        $userMessage = implode("\n", [
            'Generate a weekly review from the provided range and records.',
            'Required output JSON schema (exact keys and types, no extras):',
            json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'Validation constraints:',
            '- week_summary: string',
            '- compliance_rate: number between 0.0 and 1.0',
            '- patterns_detected: array of strings',
            '- strongest_day: string',
            '- weakest_day: string',
            '- override_analysis: string',
            '- directive: string',
            '- doctrine_alignment_score: integer between 0 and 100',
            'Input payload:',
            json_encode($inputPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);

        return [
            'system' => $systemMessage,
            'user' => $userMessage,
        ];
    }
}
