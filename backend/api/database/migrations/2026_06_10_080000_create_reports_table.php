<?php

use App\Enums\ReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('registration_number', 30)->unique();
            $table->string('tracking_code', 24)->nullable()->unique();
            $table->string('report_type', 20);
            $table->string('category_code', 10);
            $table->text('chronology');
            $table->date('incident_date');
            $table->string('incident_time', 5)->nullable();
            $table->text('incident_location');
            $table->string('location_type', 20)->nullable();
            $table->text('respondent_name')->nullable();
            $table->string('respondent_campus_status', 20)->nullable();
            $table->string('respondent_relation', 20)->nullable();
            $table->text('respondent_details')->nullable();
            $table->text('witness_info')->nullable();
            $table->text('reporter_phone_encrypted')->nullable();
            $table->string('status', 20)->default(ReportStatus::Submitted->value);
            $table->string('priority', 20)->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'submitted_at']);
            $table->index(['reporter_id', 'submitted_at']);
            $table->index('category_code');
            $table->index('report_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
