<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
