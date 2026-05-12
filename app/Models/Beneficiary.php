<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;

class Beneficiary extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'oracle';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    public function getTable(): string
    {
        return config('municipality.oracle.drivers_table', 'BENEFICIARIES');
    }

    public function getKeyName(): string
    {
        return config('municipality.oracle.driver_columns.id', 'ID');
    }

    public function phoneNumber(): ?string
    {
        return $this->stringAttribute(config('municipality.oracle.driver_columns.phone', 'MOBILE'));
    }

    public function displayName(): ?string
    {
        return $this->stringAttribute(config('municipality.oracle.driver_columns.name', 'NAME'));
    }

    public function employeeNumber(): ?string
    {
        return $this->stringAttribute(config('municipality.oracle.driver_columns.employee_number', 'EMP_NO'));
    }

    public function getAuthIdentifier()
    {
        return $this->attributeValue($this->getAuthIdentifierName());
    }

    public function getKey()
    {
        return $this->getAuthIdentifier();
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($value !== null || $key === null) {
            return $value;
        }

        foreach ($this->getAttributes() as $attributeKey => $attributeValue) {
            if (strcasecmp((string) $attributeKey, (string) $key) === 0) {
                return $attributeValue;
            }
        }

        return null;
    }

    public function toAuthPayload(): array
    {
        return [
            'oracle_driver_id' => (string) $this->getAuthIdentifier(),
            'phone' => $this->phoneNumber(),
            'name' => $this->displayName(),
            'employee_number' => $this->employeeNumber(),
        ];
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('BENEFICIARIES is a read-only Oracle integration source.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('BENEFICIARIES is a read-only Oracle integration source.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('BENEFICIARIES is a read-only Oracle integration source.');
    }

    public function fill(array $attributes): static
    {
        if ($attributes !== []) {
            throw new MassAssignmentException('BENEFICIARIES is read-only.');
        }

        return $this;
    }

    protected function performInsert(Builder $query): bool
    {
        throw new LogicException('BENEFICIARIES is a read-only Oracle integration source.');
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('BENEFICIARIES is a read-only Oracle integration source.');
    }

    private function stringAttribute(string $column): ?string
    {
        $value = $this->attributeValue($column);

        return $value === null ? null : (string) $value;
    }

    private function attributeValue(string $column): mixed
    {
        foreach ($this->getAttributes() as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return $value;
            }
        }

        return $this->getAttribute($column);
    }
}
