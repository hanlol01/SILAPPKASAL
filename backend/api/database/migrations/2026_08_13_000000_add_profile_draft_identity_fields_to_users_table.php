<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_status', 40)->nullable()->after('phone_number');
            $table->text('profile_status_other')->nullable()->after('profile_status');
            $table->text('address')->nullable()->after('profile_status_other');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['profile_status', 'profile_status_other', 'address']);
        });
    }
};
