<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterDataResource;
use App\Models\CampusStatus;
use App\Models\CaseStatus;
use App\Models\EscalationType;
use App\Models\EvidenceType;
use App\Models\LocationType;
use App\Models\PriorityLevel;
use App\Models\RecoveryStatus;
use App\Models\RecoveryType;
use App\Models\Relation;
use App\Models\ReportCategory;
use App\Models\ReportType;
use App\Models\RiskLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    /**
     * @var array<string, class-string>
     */
    private array $models = [
        'report-categories' => ReportCategory::class,
        'report-types' => ReportType::class,
        'evidence-types' => EvidenceType::class,
        'case-statuses' => CaseStatus::class,
        'risk-levels' => RiskLevel::class,
        'priority-levels' => PriorityLevel::class,
        'campus-statuses' => CampusStatus::class,
        'relations' => Relation::class,
        'location-types' => LocationType::class,
        'escalation-types' => EscalationType::class,
        'recovery-types' => RecoveryType::class,
        'recovery-statuses' => RecoveryStatus::class,
    ];

    public function index(Request $request, string $type): JsonResponse
    {
        abort_unless(array_key_exists($type, $this->models), 404);

        $includeInactive = $request->boolean('include_inactive');

        if ($includeInactive && ! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
                'errors' => null,
            ], 403);
        }

        /** @var Builder $query */
        $query = $this->models[$type]::query();

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        $items = $query
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => MasterDataResource::collection($items),
        ]);
    }
}
