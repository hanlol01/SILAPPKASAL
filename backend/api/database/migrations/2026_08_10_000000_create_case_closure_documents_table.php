<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_closure_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->foreignId('final_summary_id')->constrained('case_final_summaries')->restrictOnDelete();
            $table->string('document_number', 100)->unique();
            $table->string('storage_disk', 50);
            $table->string('storage_path', 255);
            $table->string('checksum_sha256', 64);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampsTz();

            $table->unique(['storage_disk', 'storage_path'], 'case_closure_documents_storage_unique');
            $table->index(['case_id', 'issued_at'], 'case_closure_documents_case_issued_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_closure_documents');
    }
};
