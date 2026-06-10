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
        if (! Schema::hasColumn('recommendation_statuses', 'valid_transitions')) {
            Schema::table('recommendation_statuses', function (Blueprint $table): void {
                $table->json('valid_transitions')->nullable()->after('description');
            });
        }

        Schema::create('recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->foreignId('investigation_id')->constrained('investigations')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('status_code', 10);
            $table->text('conclusion');
            $table->text('recommended_actions');
            $table->text('sanction_recommendation')->nullable();
            $table->text('recovery_recommendation')->nullable();
            $table->text('prevention_recommendation')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('status_code')->references('code')->on('recommendation_statuses')->restrictOnDelete();
            $table->index('investigation_id');
            $table->index('author_id');
            $table->index('status_code');
        });

        Schema::create('recommendation_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recommendation_id')->constrained('recommendations')->cascadeOnDelete();
            $table->string('from_status_code', 10)->nullable();
            $table->string('to_status_code', 10);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->foreign('from_status_code')->references('code')->on('recommendation_statuses')->restrictOnDelete();
            $table->foreign('to_status_code')->references('code')->on('recommendation_statuses')->restrictOnDelete();
            $table->index(['recommendation_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_status_histories');
        Schema::dropIfExists('recommendations');

        if (Schema::hasColumn('recommendation_statuses', 'valid_transitions')) {
            Schema::table('recommendation_statuses', function (Blueprint $table): void {
                $table->dropColumn('valid_transitions');
            });
        }
    }
};
