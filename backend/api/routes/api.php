<?php

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
});
