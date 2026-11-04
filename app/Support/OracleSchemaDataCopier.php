<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class OracleSchemaDataCopier
{
    private const TABLES = [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'sms_login_codes',
        'mission_locations',
        'mission_types',
        'trips',
        'trip_points',
        'trip_stops',
        'trip_passengers',
        'audit_logs',
        'fleet_admin_allowlist',
    ];

    private const DELETE_ORDER = [
        'audit_logs',
        'trip_passengers',
        'trip_stops',
        'trip_points',
        'trips',
        'personal_access_tokens',
        'sms_login_codes',
        'sessions',
        'cache_locks',
        'cache',
        'failed_jobs',
        'job_batches',
        'jobs',
        'mission_locations',
        'mission_types',
        'fleet_admin_allowlist',
    ];

    public function connection(): ConnectionInterface
    {
        return DB::connection('oracle');
    }

    public function preview(string $sourceSchema): Collection
    {
        $sourceSchema = $this->normalizeSchema($sourceSchema);
        $currentSchema = $this->currentSchema();

        return collect(self::TABLES)->map(function (string $table) use ($sourceSchema, $currentSchema): array {
            $sourceTable = strtoupper($table);
            $targetTable = strtoupper($table);
            $sourceReadable = $this->tableExists($sourceSchema, $sourceTable);
            $targetExists = $this->tableExists($currentSchema, $targetTable);
            $sourceCount = $sourceReadable ? $this->countRows($sourceSchema, $sourceTable) : null;
            $targetCount = $targetExists ? $this->countRows($currentSchema, $targetTable) : null;
            $columns = $sourceReadable && $targetExists
                ? $this->sharedColumns($sourceSchema, $sourceTable, $currentSchema, $targetTable)
                : collect();

            return [
                'table' => $targetTable,
                'source_schema' => $sourceSchema,
                'source_readable' => $sourceReadable,
                'source_count' => $sourceCount,
                'target_schema' => $currentSchema,
                'target_exists' => $targetExists,
                'target_count' => $targetCount,
                'shared_columns' => $columns->count(),
            ];
        });
    }

    public function copy(string $sourceSchema, bool $replace = false): Collection
    {
        $sourceSchema = $this->normalizeSchema($sourceSchema);
        $currentSchema = $this->currentSchema();
        $preview = $this->preview($sourceSchema);

        $missing = $preview
            ->filter(fn (array $row): bool => ! $row['source_readable'] && ! in_array($row['table'], ['SESSIONS', 'CACHE', 'CACHE_LOCKS', 'JOBS', 'JOB_BATCHES', 'FAILED_JOBS'], true))
            ->pluck('table')
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Source schema is not readable for required tables: '.$missing->implode(', '));
        }

        return DB::connection('oracle')->transaction(function () use ($sourceSchema, $currentSchema, $replace, $preview): Collection {
            if ($replace) {
                foreach (self::DELETE_ORDER as $table) {
                    $upperTable = strtoupper($table);
                    if ($this->tableExists($currentSchema, $upperTable)) {
                        $this->connection()->table($upperTable)->delete();
                    }
                }
            }

            $results = collect();

            foreach ($preview as $row) {
                if (! $row['source_readable'] || ! $row['target_exists'] || (int) $row['source_count'] === 0) {
                    $results->push($row + ['inserted' => 0, 'skipped' => true]);

                    continue;
                }

                $table = $row['table'];
                $columns = $this->sharedColumns($sourceSchema, $table, $currentSchema, $table);

                if ($columns->isEmpty()) {
                    $results->push($row + ['inserted' => 0, 'skipped' => true]);

                    continue;
                }

                $columnList = $columns->map(fn (string $column): string => $this->quote($column))->implode(', ');
                $sql = sprintf(
                    'insert into %s (%s) select %s from %s',
                    $this->quote($table),
                    $columnList,
                    $columnList,
                    $this->quote($sourceSchema).'.'.$this->quote($table),
                );

                $inserted = $this->connection()->affectingStatement($sql);
                $this->syncIdentityStart($table);

                $results->push($row + ['inserted' => $inserted, 'skipped' => false]);
            }

            return $results;
        });
    }

    public function currentSchema(): string
    {
        $row = $this->connection()->selectOne(
            'select sys_context(:namespace, :attribute) as current_schema from dual',
            ['namespace' => 'USERENV', 'attribute' => 'CURRENT_SCHEMA'],
        );

        return strtoupper((string) $row->current_schema);
    }

    private function normalizeSchema(string $schema): string
    {
        $schema = strtoupper(trim($schema));

        if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $schema)) {
            throw new InvalidArgumentException('Invalid Oracle schema name.');
        }

        if ($schema === $this->currentSchema()) {
            throw new InvalidArgumentException('Source schema must be different from the current target schema.');
        }

        return $schema;
    }

    private function tableExists(string $owner, string $table): bool
    {
        return $this->connection()
            ->table('all_tables')
            ->where('owner', strtoupper($owner))
            ->where('table_name', strtoupper($table))
            ->exists();
    }

    private function countRows(string $owner, string $table): int
    {
        $row = $this->connection()->selectOne(sprintf(
            'select count(*) as aggregate from %s.%s',
            $this->quote($owner),
            $this->quote($table),
        ));

        return (int) ($row->aggregate ?? 0);
    }

    private function sharedColumns(string $sourceOwner, string $sourceTable, string $targetOwner, string $targetTable): Collection
    {
        $sourceColumns = $this->columns($sourceOwner, $sourceTable);
        $targetColumns = $this->columns($targetOwner, $targetTable);

        return $targetColumns
            ->intersect($sourceColumns)
            ->values();
    }

    private function columns(string $owner, string $table): Collection
    {
        return $this->connection()
            ->table('all_tab_columns')
            ->where('owner', strtoupper($owner))
            ->where('table_name', strtoupper($table))
            ->orderBy('column_id')
            ->pluck('column_name')
            ->map(fn (string $column): string => strtoupper($column));
    }

    private function syncIdentityStart(string $table): void
    {
        try {
            $identityColumns = $this->connection()
                ->table('user_tab_identity_cols')
                ->where('table_name', strtoupper($table))
                ->pluck('column_name');

            foreach ($identityColumns as $column) {
                $this->connection()->statement(sprintf(
                    'alter table %s modify %s generated by default as identity (start with limit value)',
                    $this->quote($table),
                    $this->quote((string) $column),
                ));
            }
        } catch (Throwable) {
            // Older Oracle versions may not support START WITH LIMIT VALUE.
            // The copied data remains valid; future inserts should be checked manually if this fails.
        }
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', strtoupper($identifier)).'"';
    }
}
