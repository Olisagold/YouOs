<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\GenerateWeeklyReviewRequest;
use App\Models\WeeklyReview;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyReviewController extends Controller
{
    public function generate(GenerateWeeklyReviewRequest $request): JsonResponse
    {
        $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = CarbonImmutable::now()->endOfWeek(CarbonImmutable::SUNDAY);

        $logs = $request->user()
            ->disciplineLogs()
            ->whereBetween('created_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->get();

        $totalLogs = $logs->count();
        $compliedLogs = $logs->where('log_type', 'complied')->count();
        $violationLogs = $logs->where('log_type', 'violation')->count();

        $complianceRate = $totalLogs > 0
            ? round(($compliedLogs / $totalLogs) * 100, 2)
            : 0.0;

        $reviewPayload = [
            'compliance_rate' => $complianceRate,
            'total_logs' => $totalLogs,
            'complied_logs' => $compliedLogs,
            'violation_logs' => $violationLogs,
            'generated_at' => now()->toIso8601String(),
            'summary' => 'Phase 2 placeholder review. LLM generation is not enabled yet.',
        ];

        $weeklyReview = WeeklyReview::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'week_start' => $weekStart->toDateString(),
            ],
            [
                'week_end' => $weekEnd->toDateString(),
                'ai_review_json' => $reviewPayload,
            ]
        );

        return response()->json($weeklyReview);
    }

    public function index(Request $request): JsonResponse
    {
        $reviews = $request->user()
            ->weeklyReviews()
            ->orderByDesc('week_start')
            ->paginate(15);

        return response()->json($reviews);
    }

    public function show(WeeklyReview $weeklyReview): JsonResponse
    {
        $this->authorize('view', $weeklyReview);

        return response()->json($weeklyReview);
    }
}
