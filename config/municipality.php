<?php

return [
    'oracle' => [
        'vehicles_table' => env('ORACLE_VEHICLES_TABLE', 'VEHICLE'),
        'drivers_table' => env('ORACLE_DRIVERS_TABLE', 'BENEFICIARIES'),

        'vehicle_columns' => [
            'id' => env('ORACLE_VEHICLE_ID_COLUMN', 'ID'),
            'plate' => env('ORACLE_VEHICLE_PLATE_COLUMN', 'PLATE_NO'),
            'name' => env('ORACLE_VEHICLE_NAME_COLUMN', 'VEHICLE_NAME'),
            'type' => env('ORACLE_VEHICLE_TYPE_COLUMN', 'VEHICLE_TYPE'),
            'beneficiary_id' => env('ORACLE_VEHICLE_BENEFICIARY_COLUMN', 'BENEFICIARY_OID'),
        ],

        'driver_columns' => [
            'id' => env('ORACLE_DRIVER_ID_COLUMN', 'ID'),
            'phone' => env('ORACLE_DRIVER_PHONE_COLUMN', 'MOBILE'),
            'name' => env('ORACLE_DRIVER_NAME_COLUMN', 'NAME'),
            'employee_number' => env('ORACLE_DRIVER_EMPLOYEE_COLUMN', 'EMP_NO'),
        ],
    ],
];
