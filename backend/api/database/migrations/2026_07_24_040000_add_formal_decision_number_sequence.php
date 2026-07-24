<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'decisions_decision_number_unique';

    public function up(): void
    {
        $duplicateGroups = DB::table('decisions')
            ->whereNotNull('decision_number')
            ->select('decision_number')
            ->groupBy('decision_number')
            ->havingRaw('COUNT(*) > 1');
        $duplicateGroupCount = DB::query()
            ->fromSub($duplicateGroups, 'duplicate_decision_numbers')
            ->count();

        if ($duplicateGroupCount > 0) {
            throw new RuntimeException(
                "Cannot add formal decision number uniqueness: {$duplicateGroupCount} duplicate non-null decision_number group(s) must be resolved operationally.",
            );
        }

        DB::transaction(function (): void {
            Schema::create('decision_number_sequences', function (Blueprint $table): void {
                $table->smallInteger('year')->primary();
                $table->unsignedBigInteger('last_value')->default(0);
                $table->timestamps();
            });

            Schema::table('decisions', function (Blueprint $table): void {
                $table->unique('decision_number', self::UNIQUE_INDEX);
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table('decisions', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
            });

            Schema::dropIfExists('decision_number_sequences');
        });
    }
};
