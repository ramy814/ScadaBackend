<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class OracleVehicle extends Model
{
    protected $connection = 'oracle';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    public function getTable(): string
    {
        return config('municipality.oracle.vehicles_table', 'VEHICLE');
    }

    public function getKeyName(): string
    {
        return config('municipality.oracle.vehicle_columns.id', 'ID');
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('VEHICLE is a read-only Oracle integration source.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('VEHICLE is a read-only Oracle integration source.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('VEHICLE is a read-only Oracle integration source.');
    }

    public function fill(array $attributes): static
    {
        if ($attributes !== []) {
            throw new MassAssignmentException('VEHICLE is read-only.');
        }

        return $this;
    }

    protected function performInsert(Builder $query): bool
    {
        throw new LogicException('VEHICLE is a read-only Oracle integration source.');
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('VEHICLE is a read-only Oracle integration source.');
    }
}
