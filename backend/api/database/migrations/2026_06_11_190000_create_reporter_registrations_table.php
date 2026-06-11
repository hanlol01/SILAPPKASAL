<?php

use App\Enums\ReporterRegistrationStatus;
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
        Schema::create('reporter_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('registration_number', 40)->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('nim', 50)->index();
            $table->string('phone_number', 30)->nullable();
            $table->string('password_hash')->nullable();
            $table->string('status', 20)->default(ReporterRegistrationStatus::Pending->value)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['nim', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporter_registrations');
    }
};
