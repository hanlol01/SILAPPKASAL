<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualReporterStoreRequest;
use App\Http\Requests\StaffPasswordResetRequest;
use App\Http\Requests\StaffUserStoreRequest;
use App\Http\Requests\StaffUserUpdateRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Requests\UserLookupRequest;
use App\Http\Requests\UserRoleUpdateRequest;
use App\Http\Resources\UserLookupResource;
use App\Http\Resources\UserManagementResource;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $userManagementService)
    {
    }

    public function index(UserIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $users = $this->userManagementService->list($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => UserManagementResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function lookup(UserLookupRequest $request): JsonResponse
    {
        Gate::authorize('lookup', User::class);

        return response()->json([
            'success' => true,
            'message' => 'User lookup retrieved successfully',
            'data' => UserLookupResource::collection($this->userManagementService->lookup($request->validated(), $request->user())),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserManagementResource($user->load(['role', 'university', 'faculty', 'studyProgram'])),
        ]);
    }

    public function storeReporter(ManualReporterStoreRequest $request): JsonResponse
    {
        Gate::authorize('createReporter', User::class);

        $result = $this->userManagementService->createReporter($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reporter user created successfully',
            'data' => [
                'user' => new UserManagementResource($result['user']),
                'temporary_password' => $result['temporary_password'],
            ],
        ], 201);
    }

    public function staffIndex(UserIndexRequest $request): JsonResponse
    {
        Gate::authorize('manageStaff', User::class);

        $users = $this->userManagementService->listStaff($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Staff users retrieved successfully',
            'data' => UserManagementResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function storeStaff(StaffUserStoreRequest $request): JsonResponse
    {
        Gate::authorize('manageStaff', User::class);

        return response()->json([
            'success' => true,
            'message' => 'Staff user created successfully',
            'data' => new UserManagementResource($this->userManagementService->createStaff($request->validated(), $request->user())),
        ], 201);
    }

    public function updateStaff(StaffUserUpdateRequest $request, User $user): JsonResponse
    {
        Gate::authorize('manageStaff', $user);

        return response()->json([
            'success' => true,
            'message' => 'Staff user updated successfully',
            'data' => new UserManagementResource($this->userManagementService->updateStaff($user, $request->validated(), $request->user())),
        ]);
    }

    public function resetStaffPassword(StaffPasswordResetRequest $request, User $user): JsonResponse
    {
        Gate::authorize('manageStaff', $user);

        return response()->json([
            'success' => true,
            'message' => 'Staff user password reset successfully',
            'data' => new UserManagementResource($this->userManagementService->resetStaffPassword($user, $request->validated(), $request->user())),
        ]);
    }

    public function activateStaff(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manageStaff', $user);

        return response()->json([
            'success' => true,
            'message' => 'Staff user activated successfully',
            'data' => new UserManagementResource($this->userManagementService->activateStaff($user, $request->user())),
        ]);
    }

    public function deactivateStaff(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manageStaff', $user);

        return response()->json([
            'success' => true,
            'message' => 'Staff user deactivated successfully',
            'data' => new UserManagementResource($this->userManagementService->deactivateStaff($user, $request->user())),
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('activate', $user);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
            'data' => new UserManagementResource($this->userManagementService->activate($user, $request->user())),
        ]);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deactivate', $user);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully',
            'data' => new UserManagementResource($this->userManagementService->deactivate($user, $request->user())),
        ]);
    }

    public function role(UserRoleUpdateRequest $request, User $user): JsonResponse
    {
        Gate::authorize('assignRole', $user);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => new UserManagementResource($this->userManagementService->assignRole(
                $user,
                $request->user(),
                (string) $request->validated('role_code')
            )),
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        $result = $this->userManagementService->resetPassword($user, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User password reset successfully',
            'data' => [
                'user' => new UserManagementResource($result['user']),
                'temporary_password' => $result['temporary_password'],
            ],
        ]);
    }
}
