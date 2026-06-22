<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_glass_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requestor_id')->constrained('users');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->foreignId('report_id')->constrained('reports');
            $table->string('reason_category', 50);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->text('denial_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('status');
            $table->index('report_id');
            $table->index('requestor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_requests');
    }
};
