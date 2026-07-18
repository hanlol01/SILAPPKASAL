<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CampusMasterDataService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, University>
     */
    public function listUniversities(array $filters): LengthAwarePaginator
    {
        return University::query()
            ->withCount(['faculties', 'studyPrograms'])
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(! empty($filters['type']), fn (Builder $query): Builder => $query->where('type', $filters['type']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage($filters));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createUniversity(array $data, User $actor): University
    {
        return DB::transaction(function () use ($data, $actor): University {
            $university = University::query()->create($this->universityPayload($data));

            $this->audit(AuditAction::CampusUniversityCreated, $actor, $university, after: $this->universityDelta($university));

            return $university->refresh()->loadCount(['faculties', 'studyPrograms']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateUniversity(University $university, array $data, User $actor): University
    {
        return DB::transaction(function () use ($university, $data, $actor): University {
            $before = $this->universityDelta($university);

            $university->forceFill($this->universityPayload($data))->save();

            $this->audit(AuditAction::CampusUniversityUpdated, $actor, $university, before: $before, after: $this->universityDelta($university));

            return $university->refresh()->loadCount(['faculties', 'studyPrograms']);
        });
    }

    public function toggleUniversityActive(University $university, User $actor): University
    {
        return DB::transaction(function () use ($university, $actor): University {
            $before = ['is_active' => (bool) $university->is_active];
            $nextState = ! $university->is_active;

            $university->forceFill(['is_active' => $nextState])->save();

            if (! $nextState) {
                Faculty::query()->where('university_id', $university->id)->update(['is_active' => false]);
                StudyProgram::query()->where('university_id', $university->id)->update(['is_active' => false]);
            }

            $this->audit(
                $nextState ? AuditAction::CampusUniversityActivated : AuditAction::CampusUniversityDeactivated,
                $actor,
                $university,
                before: $before,
                after: ['is_active' => $nextState]
            );

            return $university->refresh()->loadCount(['faculties', 'studyPrograms']);
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Faculty>
     */
    public function listFaculties(array $filters): LengthAwarePaginator
    {
        return Faculty::query()
            ->with('university')
            ->withCount('studyPrograms')
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->when(! empty($filters['university_id']), fn (Builder $query): Builder => $query->where('university_id', $filters['university_id']))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage($filters));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFaculty(array $data, User $actor): Faculty
    {
        return DB::transaction(function () use ($data, $actor): Faculty {
            $faculty = Faculty::query()->create($this->facultyPayload($data));

            $this->audit(AuditAction::CampusFacultyCreated, $actor, $faculty, after: $this->facultyDelta($faculty));

            return $faculty->refresh()->load(['university'])->loadCount('studyPrograms');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFaculty(Faculty $faculty, array $data, User $actor): Faculty
    {
        return DB::transaction(function () use ($faculty, $data, $actor): Faculty {
            $before = $this->facultyDelta($faculty);

            $faculty->forceFill($this->facultyPayload($data))->save();

            $this->audit(AuditAction::CampusFacultyUpdated, $actor, $faculty, before: $before, after: $this->facultyDelta($faculty));

            return $faculty->refresh()->load(['university'])->loadCount('studyPrograms');
        });
    }

    public function toggleFacultyActive(Faculty $faculty, User $actor): Faculty
    {
        return DB::transaction(function () use ($faculty, $actor): Faculty {
            $faculty->loadMissing('university');
            $nextState = ! $faculty->is_active;

            if ($nextState && ! $faculty->university->is_active) {
                throw $this->unprocessable('Cannot activate a faculty while its university is inactive');
            }

            $before = ['is_active' => (bool) $faculty->is_active];
            $faculty->forceFill(['is_active' => $nextState])->save();

            if (! $nextState) {
                StudyProgram::query()->where('faculty_id', $faculty->id)->update(['is_active' => false]);
            }

            $this->audit(
                $nextState ? AuditAction::CampusFacultyActivated : AuditAction::CampusFacultyDeactivated,
                $actor,
                $faculty,
                before: $before,
                after: ['is_active' => $nextState]
            );

            return $faculty->refresh()->load(['university'])->loadCount('studyPrograms');
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, StudyProgram>
     */
    public function listStudyPrograms(array $filters): LengthAwarePaginator
    {
        return StudyProgram::query()
            ->with(['university', 'faculty'])
            ->when(! empty($filters['search']), fn (Builder $query): Builder => $this->applySearch($query, (string) $filters['search']))
            ->when(! empty($filters['university_id']), fn (Builder $query): Builder => $query->where('university_id', $filters['university_id']))
            ->when(! empty($filters['faculty_id']), fn (Builder $query): Builder => $query->where('faculty_id', $filters['faculty_id']))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage($filters));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createStudyProgram(array $data, User $actor): StudyProgram
    {
        return DB::transaction(function () use ($data, $actor): StudyProgram {
            $studyProgram = StudyProgram::query()->create($this->studyProgramPayload($data));

            $this->audit(AuditAction::CampusStudyProgramCreated, $actor, $studyProgram, after: $this->studyProgramDelta($studyProgram));

            return $studyProgram->refresh()->load(['university', 'faculty']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStudyProgram(StudyProgram $studyProgram, array $data, User $actor): StudyProgram
    {
        return DB::transaction(function () use ($studyProgram, $data, $actor): StudyProgram {
            $before = $this->studyProgramDelta($studyProgram);

            $studyProgram->forceFill($this->studyProgramPayload($data))->save();

            $this->audit(AuditAction::CampusStudyProgramUpdated, $actor, $studyProgram, before: $before, after: $this->studyProgramDelta($studyProgram));

            return $studyProgram->refresh()->load(['university', 'faculty']);
        });
    }

    public function toggleStudyProgramActive(StudyProgram $studyProgram, User $actor): StudyProgram
    {
        return DB::transaction(function () use ($studyProgram, $actor): StudyProgram {
            $studyProgram->loadMissing(['university', 'faculty']);
            $nextState = ! $studyProgram->is_active;

            if ($nextState && ! $studyProgram->university->is_active) {
                throw $this->unprocessable('Cannot activate a study program while its university is inactive');
            }

            if ($nextState && $studyProgram->faculty && ! $studyProgram->faculty->is_active) {
                throw $this->unprocessable('Cannot activate a study program while its faculty is inactive');
            }

            $before = ['is_active' => (bool) $studyProgram->is_active];
            $studyProgram->forceFill(['is_active' => $nextState])->save();

            $this->audit(
                $nextState ? AuditAction::CampusStudyProgramActivated : AuditAction::CampusStudyProgramDeactivated,
                $actor,
                $studyProgram,
                before: $before,
                after: ['is_active' => $nextState]
            );

            return $studyProgram->refresh()->load(['university', 'faculty']);
        });
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $needle = mb_strtolower(trim($search));

        return $query->where(function (Builder $query) use ($needle): void {
            $query->whereRaw('LOWER(code) LIKE ?', ["%{$needle}%"])
                ->orWhereRaw('LOWER(name) LIKE ?', ["%{$needle}%"]);
        });
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 15), 50));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function universityPayload(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'abbreviation' => $data['abbreviation'] ?? null,
            'address' => $data['address'] ?? null,
            'website' => $data['website'] ?? null,
            'email' => $data['email'] ?? null,
            'hotline' => $data['hotline'] ?? null,
            'type' => $data['type'],
            'has_faculties' => (bool) $data['has_faculties'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function facultyPayload(array $data): array
    {
        return [
            'university_id' => $data['university_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function studyProgramPayload(array $data): array
    {
        return [
            'university_id' => $data['university_id'],
            'faculty_id' => $data['faculty_id'] ?? null,
            'code' => $data['code'],
            'name' => $data['name'],
            'degree_level' => $data['degree_level'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function universityDelta(University $university): array
    {
        return $university->only(['code', 'name', 'abbreviation', 'type', 'has_faculties', 'is_active', 'sort_order']);
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyDelta(Faculty $faculty): array
    {
        return $faculty->only(['university_id', 'code', 'name', 'is_active', 'sort_order']);
    }

    /**
     * @return array<string, mixed>
     */
    private function studyProgramDelta(StudyProgram $studyProgram): array
    {
        return $studyProgram->only(['university_id', 'faculty_id', 'code', 'name', 'degree_level', 'is_active', 'sort_order']);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function audit(AuditAction $action, User $actor, University|Faculty|StudyProgram $subject, array $before = [], array $after = []): void
    {
        $this->auditLogService->record(
            action: $action,
            category: AuditCategory::System,
            severity: str_contains($action->value, 'deactivated') ? AuditSeverity::Warning : AuditSeverity::Info,
            actor: $actor,
            subject: $subject,
            metadata: [
                'code' => $subject->code,
                'name' => $subject->name,
            ],
            beforeChanges: $before,
            afterChanges: $after
        );
    }

    private function unprocessable(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422));
    }
}
