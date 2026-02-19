<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\DailyCheckinController;
use App\Http\Controllers\Api\V1\DecisionController;
use App\Http\Controllers\Api\V1\DisciplineLogController;
use App\Http\Controllers\Api\V1\DoctrineController;
use App\Http\Controllers\Api\V1\WeeklyReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::prefix('v1')->group(function (): void {
        Route::get('/doctrine', [DoctrineController::class, 'show']);
        Route::put('/doctrine', [DoctrineController::class, 'upsert']);

        Route::get('/checkin/today', [DailyCheckinController::class, 'today']);
        Route::post('/checkin', [DailyCheckinController::class, 'store']);
        Route::get('/checkins', [DailyCheckinController::class, 'index']);

        Route::post('/decisions', [DecisionController::class, 'store'])->middleware('throttle:decisions');
        Route::get('/decisions', [DecisionController::class, 'index']);
        Route::get('/decisions/{decision}', [DecisionController::class, 'show']);
        Route::patch('/decisions/{decision}/outcome', [DecisionController::class, 'updateOutcome']);

        Route::post('/discipline-log', [DisciplineLogController::class, 'store']);
        Route::get('/discipline-log', [DisciplineLogController::class, 'index']);
        Route::get('/discipline-log/streak', [DisciplineLogController::class, 'streak']);

        Route::post('/weekly-review/generate', [WeeklyReviewController::class, 'generate']);
        Route::get('/weekly-reviews', [WeeklyReviewController::class, 'index']);
        Route::get('/weekly-reviews/{weeklyReview}', [WeeklyReviewController::class, 'show']);
    });
});
