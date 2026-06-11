<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporterRegistrationIndexRequest;
use App\Http\Requests\ReporterRegistrationRejectRequest;
use App\Http\Requests\ReporterRegistrationStoreRequest;
use App\Http\Resources\ReporterRegistrationResource;
use App\Models\ReporterRegistration;
use App\Services\ReporterRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReporterRegistrationController extends Controller
{
    public function __construct(private readonly ReporterRegistrationService $registrationService)
    {
    }

    public function store(ReporterRegistrationStoreRequest $request): JsonResponse
    {
        $registration = $this->registrationService->submit($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Reporter registration request submitted successfully',
            'data' => [
                'id' => $registration->id,
                'registration_number' => $registration->registration_number,
                'status' => $registration->status->value,
                'submitted_at' => $registration->created_at?->toJSON(),
            ],
        ], 201);
    }

    public function index(ReporterRegistrationIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', ReporterRegistration::class);

        $registrations = $this->registrationService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Reporter registrations retrieved successfully',
            'data' => ReporterRegistrationResource::collection($registrations->items()),
            'meta' => [
                'current_page' => $registrations->currentPage(),
                'per_page' => $registrations->perPage(),
                'total' => $registrations->total(),
                'last_page' => $registrations->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, ReporterRegistration $reporterRegistration): JsonResponse
    {
        Gate::authorize('view', $reporterRegistration);

        return response()->json([
            'success' => true,
            'message' => 'Reporter registration retrieved successfully',
            'data' => new ReporterRegistrationResource($reporterRegistration),
        ]);
    }

    public function approve(Request $request, ReporterRegistration $reporterRegistration): JsonResponse
    {
        Gate::authorize('approve', $reporterRegistration);

        $registration = $this->registrationService->approve($reporterRegistration, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reporter registration approved successfully',
            'data' => new ReporterRegistrationResource($registration),
        ]);
    }

    public function reject(ReporterRegistrationRejectRequest $request, ReporterRegistration $reporterRegistration): JsonResponse
    {
        Gate::authorize('reject', $reporterRegistration);

        $registration = $this->registrationService->reject($reporterRegistration, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Reporter registration rejected successfully',
            'data' => new ReporterRegistrationResource($registration),
        ]);
    }
}
