<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_minutes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('case_id');
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->timestamp('occurred_at');
            $table->text('internal_summary')->nullable();
            $table->text('anonymized_summary')->nullable();
            $table->text('outcome')->nullable();
            $table->text('follow_up')->nullable();
            $table->unsignedBigInteger('supersedes_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'version'], 'case_minutes_case_version_unique');
            $table->index(['case_id', 'status'], 'case_minutes_case_status_index');
            $table->index('supersedes_id', 'case_minutes_supersedes_id_index');

            $table->foreign('case_id', 'case_minutes_case_id_foreign')
                ->references('id')->on('cases')->restrictOnDelete();
            $table->foreign('supersedes_id', 'case_minutes_supersedes_id_foreign')
                ->references('id')->on('case_minutes')->restrictOnDelete();
            $table->foreign('created_by', 'case_minutes_created_by_foreign')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by', 'case_minutes_updated_by_foreign')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('finalized_by', 'case_minutes_finalized_by_foreign')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            try {
                Schema::dropIfExists('case_minutes');
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return;
        }

        Schema::dropIfExists('case_minutes');
    }
};
