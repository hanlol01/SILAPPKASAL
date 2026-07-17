<?php

namespace App\Http\Requests\Concerns;

use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Validation\Validator;

trait ValidatesCampusSelection
{
    public function validateCampusSelection(Validator $validator): void
    {
        $universityId = $this->integer('university_id');
        $facultyId = $this->filled('faculty_id') ? $this->integer('faculty_id') : null;
        $studyProgramId = $this->integer('study_program_id');

        if (! $universityId || ! $studyProgramId) {
            return;
        }

        if ($facultyId) {
            $facultyBelongsToUniversity = Faculty::query()
                ->whereKey($facultyId)
                ->where('university_id', $universityId)
                ->where('is_active', true)
                ->exists();

            if (! $facultyBelongsToUniversity) {
                $validator->errors()->add('faculty_id', __('validation.custom.faculty_id.campus_selection'));
            }
        }

        $studyProgram = StudyProgram::query()
            ->whereKey($studyProgramId)
            ->where('university_id', $universityId)
            ->where('is_active', true)
            ->first();

        if (! $studyProgram) {
            $validator->errors()->add('study_program_id', __('validation.custom.study_program_id.university_selection'));

            return;
        }

        if ($facultyId && (int) $studyProgram->faculty_id !== $facultyId) {
            $validator->errors()->add('study_program_id', __('validation.custom.study_program_id.faculty_selection'));
        }
    }
}
