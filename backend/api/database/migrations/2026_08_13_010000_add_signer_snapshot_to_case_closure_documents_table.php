<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_closure_documents', function (Blueprint $table): void {
            $table->foreignId('signer_id')->nullable()->after('final_summary_id')->constrained('users')->restrictOnDelete();
            $table->string('signer_name')->nullable()->after('signer_id');
            $table->string('signer_identity_number', 50)->nullable()->after('signer_name');
        });
    }

    public function down(): void
    {
        Schema::table('case_closure_documents', function (Blueprint $table): void {
            $table->dropForeign(['signer_id']);
            $table->dropColumn(['signer_id', 'signer_name', 'signer_identity_number']);
        });
    }
};
