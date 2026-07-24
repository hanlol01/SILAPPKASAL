<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('forwarded_at');
            $table->timestamp('withdrawn_at')->nullable()->after('cancelled_at');
            $table->index(['status', 'cancelled_at'], 'reports_status_cancelled_at_idx');
        });

        Schema::table('cases', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable()->after('closed_at');
        });

        Schema::create('report_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('report_id')->constrained('reports')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('request_type', 32);
            $table->string('status', 32);
            $table->text('reason');
            $table->string('previous_report_status', 32);
            $table->string('previous_case_status', 32)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('resubmission_allowed')->default(false);
            $table->foreignId('supersedes_id')->nullable()->constrained('report_withdrawals')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['report_id', 'created_at'], 'report_withdrawals_report_created_idx');
            $table->index(['requester_id', 'status'], 'report_withdrawals_requester_status_idx');
            $table->index(['case_id', 'status'], 'report_withdrawals_case_status_idx');
        });

        Schema::create('report_withdrawal_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('withdrawal_id')->constrained('report_withdrawals')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('document_type', 50);
            $table->unsignedInteger('version');
            $table->string('disk', 50);
            $table->string('path', 500)->unique();
            $table->text('original_name');
            $table->string('server_mime', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['withdrawal_id', 'document_type', 'version'],
                'report_withdrawal_document_version_unique'
            );
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX report_withdrawals_one_active_per_report
                ON report_withdrawals (report_id)
                WHERE status IN ('draft', 'waiting_document', 'pending_review')"
            );
        } else {
            Schema::table('report_withdrawals', function (Blueprint $table): void {
                $table->index(['report_id', 'status'], 'report_withdrawals_report_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_withdrawal_attachments');
        Schema::dropIfExists('report_withdrawals');

        Schema::table('cases', function (Blueprint $table): void {
            $table->dropColumn('withdrawn_at');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_status_cancelled_at_idx');
            $table->dropColumn(['cancelled_at', 'withdrawn_at']);
        });
    }
};
