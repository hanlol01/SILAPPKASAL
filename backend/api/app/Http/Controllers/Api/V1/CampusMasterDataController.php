<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacultyIndexRequest;
use App\Http\Requests\StudyProgramIndexRequest;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use Illuminate\Http\JsonResponse;

class CampusMasterDataController extends Controller
{
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
}
