<?php

namespace Database\Seeders\Demo;

use App\Models\CaseStatus;
use App\Models\Faculty;
use App\Models\InvestigationStatus;
use App\Models\RecoveryStatus;
use App\Models\RecommendationStatus;
use App\Models\DecisionStatus;
use App\Models\Role;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeed
{
    public const PASSWORD = 'Demo123@';

    /**
     * @return list<string>
     */
    public static function universityCodes(): array
    {
        return ['STAI-SA', 'STAI-AMG', 'UIKHIR', 'INU-TSM', 'UID-CMS', 'STITNU-AF', 'IMA-BJR'];
    }

    public static function date(int $daysAgo = 0): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-06-20 09:00:00', config('app.timezone'))->subDays($daysAgo);
    }

    public static function slug(string $universityCode): string
    {
        return strtolower(str_replace('-', '', $universityCode));
    }

    public static function university(string $code): University
    {
        return University::query()->where('code', $code)->firstOrFail();
    }

    public static function primaryStudyProgram(University $university): StudyProgram
    {
        return StudyProgram::query()
            ->where('university_id', $university->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->firstOrFail();
    }

    public static function facultyForStudyProgram(StudyProgram $studyProgram): ?Faculty
    {
        return $studyProgram->faculty_id ? Faculty::query()->find($studyProgram->faculty_id) : null;
    }

    public static function role(string $code): Role
    {
        return Role::query()->where('code', $code)->firstOrFail();
    }

    public static function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    public static function campusEmail(string $prefix, string $universityCode, ?int $index = null): string
    {
        $suffix = $index === null || $index === 1 ? '' : (string) $index;

        return sprintf('%s%s.%s@silappkasal.test', $prefix, $suffix, self::slug($universityCode));
    }

    public static function statusCode(string $table, string $name): string
    {
        $row = DB::table($table)->where('name', $name)->first();

        if (! $row) {
            throw new RuntimeException("Missing status [{$name}] in [{$table}].");
        }

        return $row->code;
    }

    public static function caseStatus(string $name): CaseStatus
    {
        return CaseStatus::query()->where('name', $name)->firstOrFail();
    }

    public static function investigationStatus(string $name): InvestigationStatus
    {
        return InvestigationStatus::query()->where('name', $name)->firstOrFail();
    }

    public static function recommendationStatus(string $name): RecommendationStatus
    {
        return RecommendationStatus::query()->where('name', $name)->firstOrFail();
    }

    public static function decisionStatus(string $name): DecisionStatus
    {
        return DecisionStatus::query()->where('name', $name)->firstOrFail();
    }

    public static function recoveryStatus(string $name): RecoveryStatus
    {
        return RecoveryStatus::query()->where('name', $name)->firstOrFail();
    }

    public static function masterCode(string $table, int $offset = 0): string
    {
        $row = DB::table($table)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->skip($offset)
            ->first();

        if (! $row) {
            $row = DB::table($table)->where('is_active', true)->orderBy('sort_order')->first();
        }

        if (! $row) {
            throw new RuntimeException("Required master table [{$table}] has no active rows.");
        }

        return $row->code;
    }

    public static function deterministicUuid(string $key): string
    {
        $hash = md5($key);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
