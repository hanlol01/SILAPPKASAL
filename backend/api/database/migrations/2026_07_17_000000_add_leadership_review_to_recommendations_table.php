<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $table->foreignId('returned_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable()->after('returned_by');
            $table->text('revision_note')->nullable()->after('returned_at');
            $table->foreignId('approved_by')->nullable()->after('revision_note')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['returned_at', 'revision_note', 'approved_at']);
        });
    }
};
