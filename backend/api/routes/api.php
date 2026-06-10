<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'SILAPPKASAL API is healthy',
            'data' => [
                'status' => 'ok',
                'service' => 'silappkasal-api',
            ],
        ]);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('master')->group(function (): void {
        Route::get('/{type}', [MasterDataController::class, 'index'])
            ->whereIn('type', [
                'report-categories',
                'report-types',
                'evidence-types',
                'case-statuses',
                'risk-levels',
                'priority-levels',
                'campus-statuses',
                'relations',
                'location-types',
                'escalation-types',
                'recovery-types',
            ]);
    });
});
