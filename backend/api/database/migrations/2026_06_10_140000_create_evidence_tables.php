<?php

use App\Enums\EvidenceClassification;
use App\Enums\EvidenceStatus;
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
        Schema::create('evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('investigation_id')->constrained('investigations')->cascadeOnDelete();
            $table->string('evidence_type_code', 20);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('source')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->string('classification', 50)->default(EvidenceClassification::Confidential->value);
            $table->string('status', 50)->default(EvidenceStatus::Registered->value);
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('evidence_type_code')->references('code')->on('evidence_types')->restrictOnDelete();
            $table->index('investigation_id');
            $table->index('evidence_type_code');
            $table->index('submitted_by');
            $table->index('classification');
            $table->index('status');
        });

        Schema::create('evidence_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['evidence_id', 'changed_at']);
        });

        Schema::create('evidence_custody_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 50);
            $table->timestamp('event_at');
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index(['evidence_id', 'event_at']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_custody_events');
        Schema::dropIfExists('evidence_status_histories');
        Schema::dropIfExists('evidences');
    }
};
