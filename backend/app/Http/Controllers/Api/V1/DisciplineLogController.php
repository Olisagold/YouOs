<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDisciplineLogRequest;
use App\Models\Decision;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplineLogController extends Controller
{
    public function store(StoreDisciplineLogRequest $request): JsonResponse
    {
        $payload = $request->validated();

        if (isset($payload['decision_id'])) {
            $decision = Decision::findOrFail($payload['decision_id']);
            $this->authorize('view', $decision);
        }

        $disciplineLog = $request->user()->disciplineLogs()->create($payload);

        return response()->json($disciplineLog, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $disciplineLogs = $request->user()
            ->disciplineLogs()
            ->latest('created_at')
            ->paginate(15);

        return response()->json($disciplineLogs);
    }

    public function streak(Request $request): JsonResponse
    {
        $dailyLogs = $request->user()
            ->disciplineLogs()
            ->selectRaw('DATE(created_at) as log_date')
            ->selectRaw("MAX(CASE WHEN log_type = 'violation' THEN 1 ELSE 0 END) as has_violation")
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get()
            ->map(function ($row): array {
                return [
                    'date' => CarbonImmutable::parse($row->log_date),
                    'has_violation' => (bool) $row->has_violation,
                ];
            })
            ->values();

        $longestStreak = 0;
        $runningStreak = 0;
        $previousDate = null;

        foreach ($dailyLogs as $dailyLog) {
            if ($dailyLog['has_violation']) {
                $runningStreak = 0;
                $previousDate = $dailyLog['date'];
                continue;
            }

            if (
                $previousDate instanceof CarbonImmutable
                && $dailyLog['date']->isSameDay($previousDate->addDay())
            ) {
                $runningStreak++;
            } else {
                $runningStreak = 1;
            }

            $longestStreak = max($longestStreak, $runningStreak);
            $previousDate = $dailyLog['date'];
        }

        $currentStreak = 0;
        $expectedDate = null;

        foreach ($dailyLogs->reverse()->values() as $dailyLog) {
            if ($dailyLog['has_violation']) {
                break;
            }

            if (
                $expectedDate instanceof CarbonImmutable
                && ! $dailyLog['date']->isSameDay($expectedDate)
            ) {
                break;
            }

            $currentStreak++;
            $expectedDate = $dailyLog['date']->subDay();
        }

        $lastBrokenDate = $dailyLogs
            ->filter(fn (array $dailyLog): bool => $dailyLog['has_violation'])
            ->last();

        return response()->json([
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'last_broken_date' => $lastBrokenDate ? $lastBrokenDate['date']->toDateString() : null,
        ]);
    }
}
