<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('id')->constrained()->restrictOnDelete();
            $table->string('nim', 20)->nullable()->unique()->after('email');
            $table->string('nip', 20)->nullable()->unique()->after('nim');
            $table->string('phone_number', 15)->nullable()->after('nip');
            $table->boolean('is_active')->default(true)->after('password');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
            $table->dropColumn('phone_number');
            $table->dropUnique(['nip']);
            $table->dropColumn('nip');
            $table->dropUnique(['nim']);
            $table->dropColumn('nim');
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
