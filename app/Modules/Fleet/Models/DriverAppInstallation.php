<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Model;

class DriverAppInstallation extends Model
{
    protected $fillable = [
        'oracle_driver_id',
        'device_id',
        'driver_name',
        'driver_phone',
        'driver_employee_number',
        'app_version',
        'build_number',
        'package_name',
        'installer_store',
        'installed_at',
        'app_updated_at',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'build_number' => 'integer',
            'installed_at' => 'datetime',
            'app_updated_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
