<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'employee_id', 'name', 'source'])]
class TripPassenger extends Model
{
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}

