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
        Schema::create('decision_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('valid_transitions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recommendation_id')->unique()->constrained('recommendations')->cascadeOnDelete();
            $table->foreignId('recorder_id')->constrained('users')->restrictOnDelete();
            $table->string('status_code', 20);
            $table->string('outcome_code', 50);
            $table->string('decision_number', 100)->nullable();
            $table->date('decision_date');
            $table->text('decision_summary');
            $table->text('decision_content');
            $table->timestamp('recorded_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('status_code')->references('code')->on('decision_statuses')->restrictOnDelete();
            $table->index('recorder_id');
            $table->index('status_code');
            $table->index('outcome_code');
            $table->index('decision_date');
        });

        Schema::create('decision_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('decision_id')->constrained('decisions')->cascadeOnDelete();
            $table->string('from_status_code', 20)->nullable();
            $table->string('to_status_code', 20);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->foreign('from_status_code')->references('code')->on('decision_statuses')->restrictOnDelete();
            $table->foreign('to_status_code')->references('code')->on('decision_statuses')->restrictOnDelete();
            $table->index(['decision_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decision_status_histories');
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('decision_statuses');
    }
};
