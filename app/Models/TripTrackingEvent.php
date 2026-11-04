<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTrackingEvent extends Model
{
    protected $fillable = [
        'trip_id',
        'local_id',
        'event_type',
        'detected_at',
        'latitude',
        'longitude',
        'details_json',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
