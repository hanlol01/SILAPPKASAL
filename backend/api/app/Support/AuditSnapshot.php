<?php

namespace App\Support;

use App\Enums\AuditActorKind;
use App\Models\BreakGlassRequest;
use App\Models\CaseRecord;
use App\Models\CaseClosureDocument;
use App\Models\Decision;
use App\Models\Evidence;
use App\Models\Faculty;
use App\Models\Investigation;
use App\Models\Recommendation;
use App\Models\Recovery;
use App\Models\Report;
use App\Models\ReporterRegistration;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AuditSnapshot
{
    /**
     * @return array{actor_kind: string, actor_role_code: ?string, actor_display_name_safe: ?string}
     */
    public function actor(?User $actor): array
    {
        if (! $actor) {
            return [
                'actor_kind' => AuditActorKind::System->value,
                'actor_role_code' => null,
                'actor_display_name_safe' => null,
            ];
        }

        $roleCode = $actor->relationLoaded('role') ? $actor->role?->code : $actor->role()->value('code');

        if ($roleCode === 'reporter') {
            return [
                'actor_kind' => AuditActorKind::Reporter->value,
                'actor_role_code' => 'reporter',
                'actor_display_name_safe' => null,
            ];
        }

        return [
            'actor_kind' => AuditActorKind::Staff->value,
            'actor_role_code' => $roleCode,
            'actor_display_name_safe' => $this->safeStaffName($actor->name),
        ];
    }

    /**
     * @param array<string, bool|float|int|string|null> $metadata
     * @return array{subject_kind: ?string, subject_reference_safe: ?string}
     */
    public function subject(?Model $subject, array $metadata): array
    {
        if (! $subject) {
            return ['subject_kind' => null, 'subject_reference_safe' => null];
        }

        $kind = match (true) {
            $subject instanceof Report => 'report',
            $subject instanceof CaseRecord => 'case',
            $subject instanceof CaseClosureDocument => 'case_closure_document',
            $subject instanceof Investigation => 'investigation',
            $subject instanceof Recommendation => 'recommendation',
            $subject instanceof Decision => 'decision',
            $subject instanceof Recovery => 'recovery',
            $subject instanceof Evidence => 'evidence',
            $subject instanceof BreakGlassRequest => 'emergency_access',
            $subject instanceof ReporterRegistration => 'reporter_registration',
            $subject instanceof University => 'university',
            $subject instanceof Faculty => 'faculty',
            $subject instanceof StudyProgram => 'study_program',
            $subject instanceof User => 'user',
            default => 'system_record',
        };

        $reference = match (true) {
            $subject instanceof Report => $subject->registration_number,
            $subject instanceof CaseRecord => $subject->case_number,
            $subject instanceof CaseClosureDocument => $metadata['document_number'] ?? null,
            $subject instanceof Decision => $subject->decision_number,
            $subject instanceof ReporterRegistration => $subject->registration_number,
            $subject instanceof University,
            $subject instanceof Faculty,
            $subject instanceof StudyProgram => $subject->code,
            $subject instanceof User => $subject->role?->code === 'reporter' ? 'Pelapor' : null,
            default => null,
        };

        $reference ??= $metadata['case_number'] ?? $metadata['registration_number'] ?? $metadata['decision_number'] ?? $metadata['code'] ?? null;

        return [
            'subject_kind' => $kind,
            'subject_reference_safe' => is_string($reference) ? Str::limit($reference, 100, '') : null,
        ];
    }

    public function safeStaffName(?string $name): ?string
    {
        if (! is_string($name) || str_contains($name, '@') || preg_match('/\d{6,}/', $name) === 1) {
            return null;
        }

        $clean = Str::of($name)
            ->replaceMatches('/[^\pL\pM .\'\-]/u', ' ')
            ->squish()
            ->words(3, '')
            ->limit(60, '')
            ->toString();

        return $clean !== '' ? $clean : null;
    }
}
