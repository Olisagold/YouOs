<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OpenRouterUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\GenerateWeeklyReviewRequest;
use App\Models\WeeklyReview;
use App\Services\WeeklyReviewGeneratorService;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WeeklyReviewController extends Controller
{
    public function generate(
        GenerateWeeklyReviewRequest $request,
        WeeklyReviewGeneratorService $weeklyReviewGeneratorService
    ): JsonResponse
    {
        $this->authorize('create', WeeklyReview::class);

        $doctrine = $request->user()->doctrine()->first();

        if (! $doctrine) {
            return ApiError::response(
                ApiError::DOCTRINE_REQUIRED,
                'Doctrine must be set before generating weekly review.',
                422
            );
        }

        try {
            $weeklyReview = $weeklyReviewGeneratorService->generate($request->user(), $doctrine);
        } catch (ValidationException $exception) {
            return ApiError::response(
                ApiError::AI_RESPONSE_INVALID,
                'AI response did not match the expected weekly review schema.',
                422,
                [
                    'validation_errors' => $exception->errors(),
                ]
            );
        } catch (OpenRouterUnavailableException $exception) {
            return ApiError::response(
                ApiError::OPENROUTER_UNAVAILABLE,
                $exception->getMessage(),
                503,
                [
                    'status' => $exception->statusCode(),
                ]
            );
        }

        return response()->json($weeklyReview, 201);
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
