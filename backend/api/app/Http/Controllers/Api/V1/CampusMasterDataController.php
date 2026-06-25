<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFacultyRequest;
use App\Http\Requests\StoreStudyProgramRequest;
use App\Http\Requests\StoreUniversityRequest;
use App\Http\Requests\UpdateFacultyRequest;
use App\Http\Requests\UpdateStudyProgramRequest;
use App\Http\Requests\UpdateUniversityRequest;
use App\Http\Requests\FacultyIndexRequest;
use App\Http\Requests\StudyProgramIndexRequest;
use App\Http\Resources\CampusFacultyResource;
use App\Http\Resources\CampusStudyProgramResource;
use App\Http\Resources\CampusUniversityResource;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use App\Services\CampusMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CampusMasterDataController extends Controller
{
    public function __construct(private readonly CampusMasterDataService $campusMasterDataService)
    {
    }

    public function universities(): JsonResponse
    {
        $items = University::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (University $university): array => [
                'id' => $university->id,
                'code' => $university->code,
                'name' => $university->name,
                'abbreviation' => $university->abbreviation,
                'type' => $university->type,
                'has_faculties' => $university->has_faculties,
                'website' => $university->website,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => $items,
        ]);
    }

    public function faculties(FacultyIndexRequest $request): JsonResponse
    {
        $items = Faculty::query()
            ->where('university_id', $request->integer('university_id'))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $faculty): array => [
                'id' => $faculty->id,
                'code' => $faculty->code,
                'name' => $faculty->name,
                'university_id' => $faculty->university_id,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => $items,
        ]);
    }

    public function studyPrograms(StudyProgramIndexRequest $request): JsonResponse
    {
        $items = StudyProgram::query()
            ->where('university_id', $request->integer('university_id'))
            ->when($request->filled('faculty_id'), fn ($query) => $query->where('faculty_id', $request->integer('faculty_id')))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (StudyProgram $studyProgram): array => [
                'id' => $studyProgram->id,
                'code' => $studyProgram->code,
                'name' => $studyProgram->name,
                'degree_level' => $studyProgram->degree_level,
                'university_id' => $studyProgram->university_id,
                'faculty_id' => $studyProgram->faculty_id,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => $items,
        ]);
    }

    public function indexAdmin(Request $request): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        $items = $this->campusMasterDataService->listUniversities($this->filters($request, ['search', 'type', 'is_active', 'per_page']));

        return $this->paginated('Universities retrieved successfully', CampusUniversityResource::collection($items->items()), $items);
    }

    public function showUniversity(University $university): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'University retrieved successfully',
            'data' => new CampusUniversityResource($university->loadCount(['faculties', 'studyPrograms'])),
        ]);
    }

    public function storeUniversity(StoreUniversityRequest $request): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'University created successfully',
            'data' => new CampusUniversityResource($this->campusMasterDataService->createUniversity($request->validated(), $request->user())),
        ], 201);
    }

    public function updateUniversity(UpdateUniversityRequest $request, University $university): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'University updated successfully',
            'data' => new CampusUniversityResource($this->campusMasterDataService->updateUniversity($university, $request->validated(), $request->user())),
        ]);
    }

    public function toggleUniversityActive(Request $request, University $university): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'University status updated successfully',
            'data' => new CampusUniversityResource($this->campusMasterDataService->toggleUniversityActive($university, $request->user())),
        ]);
    }

    public function indexFacultiesAdmin(Request $request): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        $items = $this->campusMasterDataService->listFaculties($this->filters($request, ['search', 'university_id', 'is_active', 'per_page']));

        return $this->paginated('Faculties retrieved successfully', CampusFacultyResource::collection($items->items()), $items);
    }

    public function showFaculty(Faculty $faculty): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Faculty retrieved successfully',
            'data' => new CampusFacultyResource($faculty->load(['university'])->loadCount('studyPrograms')),
        ]);
    }

    public function storeFaculty(StoreFacultyRequest $request): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Faculty created successfully',
            'data' => new CampusFacultyResource($this->campusMasterDataService->createFaculty($request->validated(), $request->user())),
        ], 201);
    }

    public function updateFaculty(UpdateFacultyRequest $request, Faculty $faculty): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Faculty updated successfully',
            'data' => new CampusFacultyResource($this->campusMasterDataService->updateFaculty($faculty, $request->validated(), $request->user())),
        ]);
    }

    public function toggleFacultyActive(Request $request, Faculty $faculty): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Faculty status updated successfully',
            'data' => new CampusFacultyResource($this->campusMasterDataService->toggleFacultyActive($faculty, $request->user())),
        ]);
    }

    public function indexStudyProgramsAdmin(Request $request): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        $items = $this->campusMasterDataService->listStudyPrograms($this->filters($request, ['search', 'university_id', 'faculty_id', 'is_active', 'per_page']));

        return $this->paginated('Study programs retrieved successfully', CampusStudyProgramResource::collection($items->items()), $items);
    }

    public function showStudyProgram(StudyProgram $studyProgram): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Study program retrieved successfully',
            'data' => new CampusStudyProgramResource($studyProgram->load(['university', 'faculty'])),
        ]);
    }

    public function storeStudyProgram(StoreStudyProgramRequest $request): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Study program created successfully',
            'data' => new CampusStudyProgramResource($this->campusMasterDataService->createStudyProgram($request->validated(), $request->user())),
        ], 201);
    }

    public function updateStudyProgram(UpdateStudyProgramRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Study program updated successfully',
            'data' => new CampusStudyProgramResource($this->campusMasterDataService->updateStudyProgram($studyProgram, $request->validated(), $request->user())),
        ]);
    }

    public function toggleStudyProgramActive(Request $request, StudyProgram $studyProgram): JsonResponse
    {
        Gate::authorize('manage-campus-master-data');

        return response()->json([
            'success' => true,
            'message' => 'Study program status updated successfully',
            'data' => new CampusStudyProgramResource($this->campusMasterDataService->toggleStudyProgramActive($studyProgram, $request->user())),
        ]);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $keys): array
    {
        return collect($request->only($keys))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function paginated(string $message, mixed $data, mixed $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
