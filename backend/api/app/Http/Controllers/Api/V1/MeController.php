<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeOwnPasswordRequest;
use App\Http\Requests\UpdateOwnProfileRequest;
use App\Http\Resources\AccountStatusResource;
use App\Http\Resources\SelfProfileResource;
use App\Services\ReporterSelfServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeController extends Controller
{
    public function __construct(private readonly ReporterSelfServiceService $selfService)
    {
    }

    public function profile(Request $request): JsonResponse
    {
        Gate::authorize('accessReporterSelfService');

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => new SelfProfileResource($request->user()->load('role')),
        ]);
    }

    public function updateProfile(UpdateOwnProfileRequest $request): JsonResponse
    {
        Gate::authorize('accessReporterSelfService');

        $user = $this->selfService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new SelfProfileResource($user),
        ]);
    }

    public function changePassword(ChangeOwnPasswordRequest $request): JsonResponse
    {
        Gate::authorize('accessReporterSelfService');

        $this->selfService->changePassword($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
            'data' => null,
        ]);
    }

    public function accountStatus(Request $request): JsonResponse
    {
        Gate::authorize('accessReporterSelfService');

        return response()->json([
            'success' => true,
            'message' => 'Account status retrieved successfully',
            'data' => new AccountStatusResource($this->selfService->accountStatus($request->user())),
        ]);
    }
}
