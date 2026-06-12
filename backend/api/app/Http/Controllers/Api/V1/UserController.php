<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

        $users = $this->userManagementService->list($request->validated());

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
            'data' => UserLookupResource::collection($this->userManagementService->lookup($request->validated())),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserManagementResource($user->load('role')),
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
}
