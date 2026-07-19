<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Refuse a destructive shrink after the application has accepted longer values.
        if (DB::table('users')->whereRaw('LENGTH(phone_number) > 15')->exists()) {
            throw new RuntimeException('Cannot shrink users.phone_number to 15 characters while longer values exist.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 15)->nullable()->change();
        });
    }
};
