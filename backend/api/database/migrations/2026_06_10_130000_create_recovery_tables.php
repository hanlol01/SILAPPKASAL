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
        Schema::create('recovery_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('valid_transitions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('decision_id')->constrained('decisions')->cascadeOnDelete();
            $table->string('recovery_type_code', 20);
            $table->string('status_code', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('recovery_plan');
            $table->text('support_needs')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('discontinued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('recovery_type_code')->references('code')->on('recovery_types')->restrictOnDelete();
            $table->foreign('status_code')->references('code')->on('recovery_statuses')->restrictOnDelete();
            $table->index('decision_id');
            $table->index('recovery_type_code');
            $table->index('status_code');
            $table->index('created_by');
        });

        Schema::create('recovery_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recovery_id')->constrained('recoveries')->cascadeOnDelete();
            $table->string('from_status_code', 20)->nullable();
            $table->string('to_status_code', 20);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->foreign('from_status_code')->references('code')->on('recovery_statuses')->restrictOnDelete();
            $table->foreign('to_status_code')->references('code')->on('recovery_statuses')->restrictOnDelete();
            $table->index(['recovery_id', 'changed_at']);
        });

        Schema::create('recovery_monitorings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recovery_id')->constrained('recoveries')->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained('users')->restrictOnDelete();
            $table->date('monitoring_date');
            $table->string('status', 50)->default('recorded');
            $table->text('condition_summary');
            $table->text('follow_up_plan')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('recovery_id');
            $table->index('monitor_id');
            $table->index('monitoring_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_monitorings');
        Schema::dropIfExists('recovery_status_histories');
        Schema::dropIfExists('recoveries');
        Schema::dropIfExists('recovery_statuses');
    }
};
