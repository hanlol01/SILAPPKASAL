<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
            $table->string('actor_kind', 20)->default('system')->after('actor_id');
            $table->string('actor_role_code', 50)->nullable()->after('actor_kind');
            $table->string('actor_display_name_safe', 60)->nullable()->after('actor_role_code');
            $table->string('result', 20)->default('succeeded')->after('severity');
            $table->string('subject_kind', 50)->nullable()->after('subject_id');
            $table->string('subject_reference_safe', 100)->nullable()->after('subject_kind');
            $table->boolean('is_elevated_access')->default(false)->after('subject_reference_safe');
            $table->timestamp('expires_at')->nullable()->after('after_changes');
        });

        $this->backfillBounded();

        if (DB::table('audit_logs')->whereNull('public_id')->exists()) {
            throw new \RuntimeException('Audit public_id backfill did not complete.');
        }

        if (DB::table('audit_logs')->select('public_id')->groupBy('public_id')->havingRaw('count(*) > 1')->exists()) {
            throw new \RuntimeException('Audit public_id backfill produced a duplicate.');
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unique('public_id', 'audit_logs_public_id_unique');
            $table->index(['created_at', 'id'], 'audit_logs_created_id_index');
            $table->index(['category', 'created_at', 'id'], 'audit_logs_category_created_id_index');
            $table->index(['severity', 'created_at', 'id'], 'audit_logs_severity_created_id_index');
            $table->index(['result', 'created_at', 'id'], 'audit_logs_result_created_id_index');
            $table->index(['category', 'severity', 'created_at', 'id'], 'audit_logs_category_severity_created_id_index');
            $table->index(['actor_kind', 'created_at', 'id'], 'audit_logs_actor_kind_created_id_index');
            $table->index(['is_elevated_access', 'created_at', 'id'], 'audit_logs_elevated_created_id_index');
            $table->index(['expires_at', 'action', 'actor_id'], 'audit_logs_expiry_boundary_index');
        });

        $this->enforceConstraints();
    }

    public function down(): void
    {
        $this->dropConstraints();

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_expiry_boundary_index');
            $table->dropIndex('audit_logs_elevated_created_id_index');
            $table->dropIndex('audit_logs_actor_kind_created_id_index');
            $table->dropIndex('audit_logs_category_severity_created_id_index');
            $table->dropIndex('audit_logs_result_created_id_index');
            $table->dropIndex('audit_logs_severity_created_id_index');
            $table->dropIndex('audit_logs_category_created_id_index');
            $table->dropIndex('audit_logs_created_id_index');
            $table->dropUnique('audit_logs_public_id_unique');
            $table->dropColumn([
                'public_id',
                'actor_kind',
                'actor_role_code',
                'actor_display_name_safe',
                'result',
                'subject_kind',
                'subject_reference_safe',
                'is_elevated_access',
                'expires_at',
            ]);
        });
    }

    private function backfillBounded(): void
    {
        $lastId = 0;

        do {
            $rows = DB::table('audit_logs as audit')
                ->leftJoin('users', 'users.id', '=', 'audit.actor_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('audit.id', '>', $lastId)
                ->orderBy('audit.id')
                ->limit(500)
                ->get([
                    'audit.id',
                    'audit.actor_id',
                    'audit.action',
                    'audit.subject_type',
                    'audit.metadata',
                    'audit.created_at',
                    'users.name as actor_name',
                    'roles.code as actor_role_code',
                ]);

            foreach ($rows as $row) {
                $roleCode = $row->actor_role_code ? (string) $row->actor_role_code : null;
                $actorKind = $row->actor_id === null ? 'system' : ($roleCode === 'reporter' ? 'reporter' : 'staff');
                $safeName = $actorKind === 'staff' ? $this->safeStaffName($row->actor_name) : null;
                $metadata = json_decode((string) ($row->metadata ?? '{}'), true);
                $elevated = is_array($metadata) && ($metadata['is_elevated_access'] ?? false) === true;

                DB::table('audit_logs')->where('id', $row->id)->update([
                    'public_id' => (string) Str::uuid(),
                    'actor_kind' => $actorKind,
                    'actor_role_code' => $roleCode,
                    'actor_display_name_safe' => $safeName,
                    'result' => match ((string) $row->action) {
                        'auth.login_failed' => 'failed',
                        'security.access_denied' => 'denied',
                        default => 'succeeded',
                    },
                    'subject_kind' => $this->legacySubjectKind($row->subject_type),
                    'subject_reference_safe' => null,
                    'is_elevated_access' => $elevated,
                    'expires_at' => (string) $row->action === 'auth.login_failed' && $row->actor_id === null
                        ? CarbonImmutable::parse($row->created_at)->addDays(7)
                        : null,
                ]);

                $lastId = (int) $row->id;
            }
        } while ($rows->isNotEmpty());
    }

    private function enforceConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN public_id SET NOT NULL');
            DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_expires_at_boundary CHECK (expires_at IS NULL OR (action = 'auth.login_failed' AND actor_id IS NULL))");
            DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_result_values CHECK (result IN ('succeeded', 'failed', 'denied'))");
            DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_result_boundary CHECK ((action <> 'auth.login_failed' OR result = 'failed') AND (action <> 'security.access_denied' OR result = 'denied'))");
            DB::statement(<<<'SQL'
                CREATE FUNCTION prevent_audit_public_id_change() RETURNS trigger AS $$
                BEGIN
                    IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
                        RAISE EXCEPTION 'audit public_id is immutable';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::statement('CREATE TRIGGER audit_logs_public_id_immutable BEFORE UPDATE ON audit_logs FOR EACH ROW EXECUTE FUNCTION prevent_audit_public_id_change()');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER audit_logs_public_id_required_insert BEFORE INSERT ON audit_logs WHEN NEW.public_id IS NULL BEGIN SELECT RAISE(ABORT, 'audit public_id is required'); END");
            DB::statement("CREATE TRIGGER audit_logs_public_id_immutable BEFORE UPDATE OF public_id ON audit_logs WHEN NEW.public_id IS NULL OR NEW.public_id <> OLD.public_id BEGIN SELECT RAISE(ABORT, 'audit public_id is immutable'); END");
            DB::statement("CREATE TRIGGER audit_logs_expires_at_boundary_insert BEFORE INSERT ON audit_logs WHEN NEW.expires_at IS NOT NULL AND (NEW.action <> 'auth.login_failed' OR NEW.actor_id IS NOT NULL) BEGIN SELECT RAISE(ABORT, 'invalid audit expiry'); END");
            DB::statement("CREATE TRIGGER audit_logs_expires_at_boundary_update BEFORE UPDATE OF expires_at, action, actor_id ON audit_logs WHEN NEW.expires_at IS NOT NULL AND (NEW.action <> 'auth.login_failed' OR NEW.actor_id IS NOT NULL) BEGIN SELECT RAISE(ABORT, 'invalid audit expiry'); END");
            DB::statement("CREATE TRIGGER audit_logs_result_values_insert BEFORE INSERT ON audit_logs WHEN NEW.result NOT IN ('succeeded', 'failed', 'denied') BEGIN SELECT RAISE(ABORT, 'invalid audit result'); END");
            DB::statement("CREATE TRIGGER audit_logs_result_values_update BEFORE UPDATE OF result ON audit_logs WHEN NEW.result NOT IN ('succeeded', 'failed', 'denied') BEGIN SELECT RAISE(ABORT, 'invalid audit result'); END");
            DB::statement("CREATE TRIGGER audit_logs_action_result_boundary_insert BEFORE INSERT ON audit_logs WHEN (NEW.action = 'auth.login_failed' AND NEW.result <> 'failed') OR (NEW.action = 'security.access_denied' AND NEW.result <> 'denied') BEGIN SELECT RAISE(ABORT, 'invalid action result'); END");
            DB::statement("CREATE TRIGGER audit_logs_action_result_boundary_update BEFORE UPDATE OF action, result ON audit_logs WHEN (NEW.action = 'auth.login_failed' AND NEW.result <> 'failed') OR (NEW.action = 'security.access_denied' AND NEW.result <> 'denied') BEGIN SELECT RAISE(ABORT, 'invalid action result'); END");

            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->change();
        });
    }

    private function dropConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_public_id_immutable ON audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS prevent_audit_public_id_change()');
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_result_boundary');
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_result_values');
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_expires_at_boundary');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_public_id_required_insert');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_public_id_immutable');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_expires_at_boundary_insert');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_expires_at_boundary_update');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_result_values_insert');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_result_values_update');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_action_result_boundary_insert');
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_action_result_boundary_update');
        }
    }

    private function safeStaffName(mixed $name): ?string
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

    private function legacySubjectKind(mixed $type): ?string
    {
        if (! is_string($type) || $type === '') {
            return null;
        }

        return match (class_basename($type)) {
            'Report' => 'report',
            'CaseRecord' => 'case',
            'Investigation' => 'investigation',
            'Recommendation' => 'recommendation',
            'Decision' => 'decision',
            'Recovery' => 'recovery',
            'Evidence', 'ReportEvidenceSubmission' => 'evidence',
            'BreakGlassRequest' => 'emergency_access',
            'ReporterRegistration' => 'reporter_registration',
            'University' => 'university',
            'Faculty' => 'faculty',
            'StudyProgram' => 'study_program',
            'User' => 'user',
            default => 'system_record',
        };
    }
};
