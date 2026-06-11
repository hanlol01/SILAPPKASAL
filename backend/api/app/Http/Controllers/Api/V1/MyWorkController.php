<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyWorkIndexRequest;
use App\Http\Resources\MyWorkResource;
use App\Services\MyWorkService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MyWorkController extends Controller
{
    public function __construct(private readonly MyWorkService $myWorkService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewMyWork');

        return response()->json([
            'success' => true,
            'message' => 'My work summary retrieved successfully',
            'data' => new MyWorkResource($this->myWorkService->summary($request->user())),
        ]);
    }

    public function cases(MyWorkIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewMyWork');

        return $this->paginatedResponse(
            'My work cases retrieved successfully',
            $this->myWorkService->cases($request->user(), $request->validated())
        );
    }

    public function investigations(MyWorkIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewMyWork');

        return $this->paginatedResponse(
            'My work investigations retrieved successfully',
            $this->myWorkService->investigations($request->user(), $request->validated())
        );
    }

    public function recommendations(MyWorkIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewMyWork');

        return $this->paginatedResponse(
            'My work recommendations retrieved successfully',
            $this->myWorkService->recommendations($request->user(), $request->validated())
        );
    }

    /**
     * @param LengthAwarePaginator<int, array<string, mixed>> $paginator
     */
    private function paginatedResponse(string $message, LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => MyWorkResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
