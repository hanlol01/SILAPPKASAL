<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_categories', function (Blueprint $table) {
            $this->baseColumns($table, 10, 100);
            $table->text('examples')->nullable()->after('description');
            $table->string('legal_basis', 255)->nullable()->after('examples');
        });

        foreach ($this->simpleTables() as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $this->baseColumns($table);
            });
        }

        Schema::create('case_statuses', function (Blueprint $table) {
            $this->baseColumns($table, 10, 100);
            $table->integer('workflow_stage')->nullable()->after('description');
            $table->string('stage_name', 30)->nullable()->after('workflow_stage');
            $table->boolean('is_terminal')->default(false)->after('stage_name');
            $table->string('responsible_role', 20)->nullable()->after('is_terminal');
            $table->jsonb('valid_transitions')->nullable()->after('responsible_role');
        });

        Schema::create('notification_types', function (Blueprint $table) {
            $this->baseColumns($table, 10, 100);
            $table->string('channel', 15)->after('description');
            $table->string('template_key', 50)->after('channel');
            $table->string('recipient_role', 20)->after('template_key');
            $table->string('classification', 20)->after('recipient_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_types');
        Schema::dropIfExists('case_statuses');

        foreach (array_reverse($this->simpleTables()) as $tableName) {
            Schema::dropIfExists($tableName);
        }

        Schema::dropIfExists('report_categories');
    }

    /**
     * @return list<string>
     */
    private function simpleTables(): array
    {
        return [
            'report_types',
            'evidence_types',
            'investigation_statuses',
            'recommendation_statuses',
            'risk_levels',
            'priority_levels',
            'campus_statuses',
            'relations',
            'location_types',
            'escalation_types',
            'recovery_types',
            'sanction_types',
        ];
    }

    private function baseColumns(Blueprint $table, int $codeLength = 20, int $nameLength = 100): void
    {
        $table->id();
        $table->string('code', $codeLength)->unique();
        $table->string('name', $nameLength);
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order');
        $table->timestamps();
    }
};
