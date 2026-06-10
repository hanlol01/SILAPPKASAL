<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('investigation_statuses', 'valid_transitions')) {
            Schema::table('investigation_statuses', function (Blueprint $table): void {
                $table->json('valid_transitions')->nullable()->after('description');
            });
        }

        Schema::create('investigations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->foreignId('lead_investigator_id')->constrained('users')->restrictOnDelete();
            $table->string('status_code', 10);
            $table->text('plan_summary')->nullable();
            $table->text('findings')->nullable();
            $table->text('conclusion')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('status_code')->references('code')->on('investigation_statuses')->restrictOnDelete();
            $table->index('status_code');
            $table->index('lead_investigator_id');
        });

        Schema::create('investigation_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('investigation_id')->constrained('investigations')->cascadeOnDelete();
            $table->foreignId('investigator_id')->constrained('users')->restrictOnDelete();
            $table->string('activity_type', 50);
            $table->date('activity_date');
            $table->text('description');
            $table->text('findings')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['investigation_id', 'activity_date']);
            $table->index('investigator_id');
            $table->index('activity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investigation_activities');
        Schema::dropIfExists('investigations');

        if (Schema::hasColumn('investigation_statuses', 'valid_transitions')) {
            Schema::table('investigation_statuses', function (Blueprint $table): void {
                $table->dropColumn('valid_transitions');
            });
        }
    }
};
