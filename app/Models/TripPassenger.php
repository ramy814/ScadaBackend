<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPassenger extends Model
{
    protected $fillable = ['trip_id', 'employee_id', 'name', 'source'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
