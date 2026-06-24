<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('faculty_id')
                ->nullable()
                ->after('university_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('study_program_id')
                ->nullable()
                ->after('faculty_id')
                ->constrained('study_programs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('study_program_id');
            $table->dropConstrainedForeignId('faculty_id');
        });
    }
};
