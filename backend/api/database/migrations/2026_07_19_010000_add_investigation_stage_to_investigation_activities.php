<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigation_activities', function (Blueprint $table): void {
            $table->string('investigation_stage_code', 10)->nullable()->after('activity_type');
            $table->foreign('investigation_stage_code')
                ->references('code')
                ->on('investigation_statuses')
                ->restrictOnDelete();
            $table->index(
                ['investigation_id', 'investigation_stage_code'],
                'investigation_activities_investigation_stage_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('investigation_activities', function (Blueprint $table): void {
            $table->dropForeign(['investigation_stage_code']);
            $table->dropIndex('investigation_activities_investigation_stage_index');
            $table->dropColumn('investigation_stage_code');
        });
    }
};
