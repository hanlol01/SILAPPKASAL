<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universities', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('abbreviation', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('hotline', 30)->nullable();
            $table->string('type', 20)->default('universitas');
            $table->boolean('has_faculties')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faculties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['university_id', 'code']);
            $table->index('university_id');
        });

        Schema::create('study_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('degree_level', 10)->default('S1');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['university_id', 'code']);
            $table->index('university_id');
            $table->index('faculty_id');
            $table->index(['university_id', 'faculty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_programs');
        Schema::dropIfExists('faculties');
        Schema::dropIfExists('universities');
    }
};
