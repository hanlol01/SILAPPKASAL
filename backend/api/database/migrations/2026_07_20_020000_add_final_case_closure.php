<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recoveries', function (Blueprint $table): void {
            $table->text('discontinuation_reason')->nullable()->after('notes');
        });

        Schema::create('case_final_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->string('outcome_code', 50);
            $table->date('completion_date');
            $table->text('official_statement');
            $table->text('investigation_summary')->nullable();
            $table->text('recommendation_result')->nullable();
            $table->text('decision_result')->nullable();
            $table->text('recovery_result')->nullable();
            $table->text('actions_completed')->nullable();
            $table->text('actions_uncompleted')->nullable();
            $table->text('follow_up_or_referral')->nullable();
            $table->text('closing_explanation');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['outcome_code', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_final_summaries');

        Schema::table('recoveries', function (Blueprint $table): void {
            $table->dropColumn('discontinuation_reason');
        });
    }
};
