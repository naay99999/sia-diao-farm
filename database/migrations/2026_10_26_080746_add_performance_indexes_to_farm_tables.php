<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes per table. The database does not index foreign keys on its own, and the records pages filter by
     * each table's own date and sort by it and then `id`, so the date indexes carry `id` too.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    private array $indexes = [
        'care_records' => [['orchard_id', 'performed_on'], ['performed_on', 'id'], ['created_via', 'created_at']],
        'care_record_tree' => [['tree_id']],
        'care_record_tree_issue' => [['tree_issue_id']],
        'harvests' => [['tree_id', 'harvested_on'], ['harvested_on', 'id'], ['created_via', 'created_at']],
        'sales' => [['orchard_id', 'sold_on'], ['sold_on', 'id'], ['created_via', 'created_at']],
        'expenses' => [['season_id', 'spent_on'], ['spent_on', 'id'], ['created_via', 'created_at']],
        'observations' => [['observed_on', 'id'], ['created_via', 'created_at']],
        'observation_photos' => [['observation_id']],
        'tree_issues' => [['tree_id']],
        'farm_notes' => [['tree_id'], ['finding_id'], ['created_via', 'created_at']],
        'seasons' => [['orchard_id', 'ended_on']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $columns) {
                    $table->index($columns);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $columns) {
                    $table->dropIndex($columns);
                }
            });
        }
    }
};
