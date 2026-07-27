<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private array $foreignKeys = [
        'inspector_teams' => [
            'INSPECTOR_OID' => 'inspectors',
            'TEAM_OID' => 'teams',
        ],
        'team_wells' => [
            'TEAM_OID' => 'teams',
            'WELL_OID' => 'wells',
        ],
        'well_generators' => [
            'WELL_OID' => 'wells',
        ],
        'well_readings' => [
            'WELL_OID' => 'wells',
            'INSPECTOR_OID' => 'inspectors',
        ],
        'diesel_inventories' => [
            'WELL_OID' => 'wells',
            'INSPECTOR_OID' => 'inspectors',
        ],
        'well_notifications' => [
            'WELL_OID' => 'wells',
            'INSPECTOR_OID' => 'inspectors',
        ],
        'well_media' => [
            'WELL_OID' => 'wells',
        ],
    ];

    public function up(): void
    {
        $this->rebuildForeignKeys();
    }

    public function down(): void
    {
        // This safety rule is deliberately preserved during rollback.
        $this->rebuildForeignKeys();
    }

    private function rebuildForeignKeys(): void
    {
        foreach ($this->foreignKeys as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach (array_keys($columns) as $column) {
                    $blueprint->dropForeign([$column]);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column => $parentTable) {
                    $blueprint->foreign($column)
                        ->references('OID')
                        ->on($parentTable);
                }
            });
        }
    }
};
