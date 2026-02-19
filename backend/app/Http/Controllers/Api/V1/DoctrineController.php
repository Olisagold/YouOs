<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpsertDoctrineRequest;
use App\Models\Doctrine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctrineController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $doctrine = $request->user()->doctrine;

        if (! $doctrine) {
            return response()->json([
                'message' => 'Doctrine not found.',
            ], 404);
        }

        $this->authorize('view', $doctrine);

        return response()->json($doctrine);
    }

    public function upsert(UpsertDoctrineRequest $request): JsonResponse
    {
        $existingDoctrine = $request->user()->doctrine;

        if ($existingDoctrine) {
            $this->authorize('update', $existingDoctrine);
        }

        $doctrine = Doctrine::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated()
        );

        return response()->json($doctrine);
    }
}
