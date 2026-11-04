<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Support\OracleSchemaDataCopier;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('oracle:check', function () {
    if (! extension_loaded('oci8')) {
        $this->error('امتداد PHP oci8 غير مفعل. ثبت Oracle Instant Client ثم فعل oci8 قبل فحص الاتصال.');

        return self::FAILURE;
    }

    try {
        $result = DB::connection('oracle')->selectOne('select 1 as ok from dual');
    } catch (Throwable $exception) {
        $this->error('فشل الاتصال بقاعدة Oracle.');
        $this->line($exception->getMessage());

        return self::FAILURE;
    }

    $this->info('تم الاتصال بقاعدة Oracle بنجاح.');
    $this->line('Result: '.json_encode($result, JSON_UNESCAPED_UNICODE));

    return self::SUCCESS;
})->purpose('Check the configured Oracle connection');

Artisan::command('oracle:copy-schema-data {source_schema : Old Oracle schema owner} {--execute : Actually copy rows} {--replace : Delete target rows before copying}', function (OracleSchemaDataCopier $copier) {
    $sourceSchema = (string) $this->argument('source_schema');
    $execute = (bool) $this->option('execute');
    $replace = (bool) $this->option('replace');

    try {
        $rows = $execute
            ? $copier->copy($sourceSchema, $replace)
            : $copier->preview($sourceSchema);
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
    }

    $this->table(
        $execute
            ? ['table', 'source_count', 'target_count_before', 'inserted', 'skipped']
            : ['table', 'source_readable', 'source_count', 'target_exists', 'target_count', 'shared_columns'],
        $rows->map(function (array $row) use ($execute): array {
            if ($execute) {
                return [
                    $row['table'],
                    $row['source_count'] ?? '-',
                    $row['target_count'] ?? '-',
                    $row['inserted'] ?? 0,
                    ($row['skipped'] ?? false) ? 'yes' : 'no',
                ];
            }

            return [
                $row['table'],
                $row['source_readable'] ? 'yes' : 'no',
                $row['source_count'] ?? '-',
                $row['target_exists'] ? 'yes' : 'no',
                $row['target_count'] ?? '-',
                $row['shared_columns'],
            ];
        })->all(),
    );

    if (! $execute) {
        $this->warn('Dry run only. Add --execute to copy rows. Add --replace to clear CONTROL_UNIT target rows first.');
    }

    return self::SUCCESS;
})->purpose('Copy managed fleet data from an old Oracle schema into the current CONTROL_UNIT schema');
