<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained('reports')->cascadeOnDelete();
            $table->string('registration_number', 30);
            $table->string('case_number', 30)->unique();
            $table->string('status_code', 10);
            $table->string('risk_level_code', 10)->nullable();
            $table->string('priority_code', 10)->nullable();
            $table->integer('current_stage')->default(2);
            $table->timestamp('forwarded_at');
            $table->timestamp('assessment_at')->nullable();
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('recommendation_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalation_type', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('status_code')->references('code')->on('case_statuses');
            $table->foreign('risk_level_code')->references('code')->on('risk_levels');
            $table->foreign('priority_code')->references('code')->on('priority_levels');
            $table->index('registration_number');
            $table->index('status_code');
            $table->index('risk_level_code');
            $table->index(['status_code', 'forwarded_at']);
        });

        Schema::create('case_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('satgas_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'satgas_id']);
            $table->index('satgas_id');
            $table->index(['satgas_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_assignments');
        Schema::dropIfExists('cases');
    }
};
