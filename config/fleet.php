<?php

return [
    'oracle' => [
        'vehicles_table' => env('ORACLE_VEHICLES_TABLE', 'VEHICLE'),
        'drivers_table' => env('ORACLE_DRIVERS_TABLE', 'BENEFICIARIES'),

        'vehicle_columns' => [
            'id' => env('ORACLE_VEHICLE_ID_COLUMN', 'OID'),
            'plate' => env('ORACLE_VEHICLE_PLATE_COLUMN', 'PLATE_NUM'),
            'name' => env('ORACLE_VEHICLE_NAME_COLUMN', 'VEHICLE_NUM'),
            'type' => env('ORACLE_VEHICLE_TYPE_COLUMN', 'VEHICLE_TYPE_OID'),
            'beneficiary_id' => env('ORACLE_VEHICLE_BENEFICIARY_COLUMN', 'BENEFICIARY_OID'),
            'active' => env('ORACLE_VEHICLE_ACTIVE_COLUMN', 'IS_ACTIVE'),
        ],

        'driver_columns' => [
            'id' => env('ORACLE_DRIVER_ID_COLUMN', 'OID'),
            'phone' => env('ORACLE_DRIVER_PHONE_COLUMN', 'MOBILE_NUM'),
            'name' => env('ORACLE_DRIVER_NAME_COLUMN', 'FULL_NAME'),
            'employee_number' => env('ORACLE_DRIVER_EMPLOYEE_COLUMN', 'NATIONAL_ID'),
            'active' => env('ORACLE_DRIVER_ACTIVE_COLUMN', 'IS_ACTIVE'),
        ],
    ],
];
