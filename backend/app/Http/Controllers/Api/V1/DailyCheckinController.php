<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDailyCheckinRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyCheckinController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $checkin = $request->user()
            ->dailyCheckins()
            ->whereDate('created_at', CarbonImmutable::today())
            ->latest('created_at')
            ->first();

        if (! $checkin) {
            return response()->json([
                'message' => 'No daily check-in found for today.',
            ], 404);
        }

        $this->authorize('view', $checkin);

        return response()->json($checkin);
    }

    public function store(StoreDailyCheckinRequest $request): JsonResponse
    {
        $alreadyCheckedIn = $request->user()
            ->dailyCheckins()
            ->whereDate('created_at', CarbonImmutable::today())
            ->exists();

        if ($alreadyCheckedIn) {
            return response()->json([
                'error' => [
                    'code' => 'daily_checkin_exists',
                    'message' => 'A daily check-in already exists for today.',
                ],
            ], 409);
        }

        $checkin = $request->user()->dailyCheckins()->create([
            ...$request->validated(),
            'checkin_date' => CarbonImmutable::today()->toDateString(),
        ]);

        return response()->json($checkin, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $checkins = $request->user()
            ->dailyCheckins()
            ->latest('created_at')
            ->paginate(15);

        return response()->json($checkins);
    }
}
