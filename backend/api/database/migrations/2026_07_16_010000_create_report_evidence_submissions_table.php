<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_evidence_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum_sha256', 64);
            $table->string('storage_disk', 50);
            $table->string('storage_path')->unique();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['report_id', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_evidence_submissions');
    }
};
