<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('university_id')
                ->nullable()
                ->after('role_id')
                ->constrained()
                ->nullOnDelete();

            $table->dropUnique(['nim']);
            $table->unique(['university_id', 'nim']);
        });

        Schema::table('reporter_registrations', function (Blueprint $table): void {
            $table->foreignId('university_id')
                ->nullable()
                ->after('registration_number')
                ->constrained()
                ->nullOnDelete();
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
        Schema::table('reporter_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('study_program_id');
            $table->dropConstrainedForeignId('faculty_id');
            $table->dropConstrainedForeignId('university_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['university_id', 'nim']);
            $table->unique('nim');
            $table->dropConstrainedForeignId('university_id');
        });
    }
};
