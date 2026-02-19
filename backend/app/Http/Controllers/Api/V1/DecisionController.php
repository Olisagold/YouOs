<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OpenRouterUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDecisionRequest;
use App\Http\Requests\V1\UpdateDecisionOutcomeRequest;
use App\Models\Decision;
use App\Services\OpenRouterService;
use App\Support\ApiError;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DecisionController extends Controller
{
    public function store(StoreDecisionRequest $request, OpenRouterService $openRouterService): JsonResponse
    {
        $user = $request->user();
        $doctrine = $user->doctrine()->first();

        if (! $doctrine) {
            return ApiError::response(
                ApiError::DOCTRINE_REQUIRED,
                'Doctrine must be set before creating decisions.',
                422
            );
        }

        $todayCheckin = $user->dailyCheckins()
            ->whereDate('created_at', CarbonImmutable::today())
            ->latest('created_at')
            ->first();

        if (! $todayCheckin) {
            return ApiError::response(
                ApiError::DAILY_CHECKIN_REQUIRED,
                'Daily check-in is required before creating decisions.',
                422
            );
        }

        try {
            $aiResult = $openRouterService->analyzeDecision(
                $doctrine,
                $todayCheckin,
                $request->validated('category'),
                $request->validated('context_json')
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
        } catch (ValidationException $exception) {
            return ApiError::response(
                ApiError::AI_RESPONSE_INVALID,
                'AI response did not match the expected schema.',
                422,
                [
                    'validation_errors' => $exception->errors(),
                ]
            );
        }

        $decision = $user->decisions()->create([
            'category' => $request->validated('category'),
            'context_json' => $request->validated('context_json'),
            'ai_response_json' => $aiResult['structured'],
            'raw_ai_response' => $aiResult['raw'],
            'final_choice' => null,
            'outcome_notes' => null,
        ]);

        return response()->json([
            'decision' => $decision,
            'ai_response' => $decision->ai_response_json,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'category' => ['nullable', Rule::in(Decision::CATEGORIES)],
        ])->validate();

        $decisionsQuery = $request->user()->decisions()->latest('created_at');

        if (isset($validated['category'])) {
            $decisionsQuery->where('category', $validated['category']);
        }

        return response()->json($decisionsQuery->paginate(15));
    }

    public function show(Decision $decision): JsonResponse
    {
        $this->authorize('view', $decision);

        return response()->json($decision);
    }

    public function updateOutcome(UpdateDecisionOutcomeRequest $request, Decision $decision): JsonResponse
    {
        $this->authorize('update', $decision);

        $decision->update($request->validated());

        return response()->json($decision);
    }
}
