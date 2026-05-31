<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $fillable = [
        'local_id',
        'driver_id',
        'vehicle_id',
        'oracle_driver_id',
        'driver_name',
        'driver_phone',
        'driver_employee_number',
        'oracle_vehicle_id',
        'vehicle_plate',
        'vehicle_name',
        'vehicle_type',
        'status',
        'started_at',
        'ended_at',
        'origin_type',
        'origin_id',
        'origin_text',
        'destination_type',
        'destination_id',
        'destination_text',
        'mission_type',
        'notes',
        'start_odometer',
        'end_odometer',
        'points_expected',
        'points_received',
        'client_checksum',
        'server_checksum',
        'confirmed_at',
        'wait_minutes',
        'virtual_fuel_liters',
        'review_status',
        'review_note',
        'reviewed_at',
        'reviewed_by',
        'clarification_response',
        'clarification_responded_at',
    ];

    protected $appends = ['driver', 'vehicle'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'clarification_responded_at' => 'datetime',
            'virtual_fuel_liters' => 'decimal:3',
        ];
    }

    public function getDriverAttribute(): object
    {
        return (object) [
            'id' => $this->oracle_driver_id,
            'oracle_driver_id' => $this->oracle_driver_id,
            'name' => $this->driver_name,
            'phone' => $this->driver_phone,
            'employee_number' => $this->driver_employee_number,
        ];
    }

    public function getVehicleAttribute(): object
    {
        return (object) [
            'id' => $this->oracle_vehicle_id,
            'oracle_vehicle_id' => $this->oracle_vehicle_id,
            'plate_number' => $this->vehicle_plate,
            'name' => $this->vehicle_name,
            'vehicle_type' => $this->vehicle_type,
        ];
    }

    public function points(): HasMany
    {
        return $this->hasMany(TripPoint::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class);
    }
}
