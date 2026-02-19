<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDecisionRequest;
use App\Http\Requests\V1\UpdateDecisionOutcomeRequest;
use App\Models\Decision;
use App\Support\ApiError;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DecisionController extends Controller
{
    public function store(StoreDecisionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->doctrine()->exists()) {
            return ApiError::response(
                ApiError::DOCTRINE_REQUIRED,
                'Doctrine must be set before creating decisions.',
                422
            );
        }

        $hasTodayCheckin = $user->dailyCheckins()
            ->whereDate('created_at', CarbonImmutable::today())
            ->exists();

        if (! $hasTodayCheckin) {
            return ApiError::response(
                ApiError::DAILY_CHECKIN_REQUIRED,
                'Daily check-in is required before creating decisions.',
                422
            );
        }

        $decision = $user->decisions()->create([
            'category' => $request->validated('category'),
            'context_json' => $request->validated('context_json'),
            'ai_response_json' => null,
            'final_choice' => null,
            'outcome_notes' => null,
        ]);

        return response()->json($decision, 201);
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
