<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()
            ->with('actor.role')
            ->latest('created_at')
            ->latest('id');

        foreach (['category', 'severity', 'action', 'actor_id', 'subject_type', 'subject_id', 'request_id'] as $filter) {
            $query->when($request->filled($filter), fn ($query) => $query->where($filter, $request->validated($filter)));
        }

        $query->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')));

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => AuditLogResource::collection($query->paginate(25))->response()->getData(true),
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        Gate::authorize('view', $auditLog);

        return response()->json([
            'success' => true,
            'message' => 'Audit log retrieved successfully',
            'data' => new AuditLogResource($auditLog->load('actor.role')),
        ]);
    }
}
