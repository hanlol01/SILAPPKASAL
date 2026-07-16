<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table): void {
            $table->string('storage_disk')->nullable()->after('checksum_sha256');
            $table->string('storage_path')->nullable()->after('storage_disk');
            $table->foreignId('file_uploaded_by')->nullable()->after('storage_path')->constrained('users')->nullOnDelete();
            $table->timestamp('file_uploaded_at')->nullable()->after('file_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('file_uploaded_by');
            $table->dropColumn(['storage_disk', 'storage_path', 'file_uploaded_at']);
        });
    }
};
