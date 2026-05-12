<?php

namespace App\Repositories;

use App\Models\Beneficiary;
use Illuminate\Support\Collection;

class MunicipalityDriverRepository
{
    public function __construct(private readonly OracleReadiness $readiness) {}

    public function findBeneficiaryByPhone(string $phone): ?Beneficiary
    {
        if (! $this->readiness->available()) {
            return null;
        }

        $columns = config('municipality.oracle.driver_columns');

        return Beneficiary::query()
            ->where($columns['phone'], $phone)
            ->first();
    }

    public function findByPhone(string $phone): ?array
    {
        $beneficiary = $this->findBeneficiaryByPhone($phone);

        if (! $beneficiary) {
            return null;
        }

        return [
            'oracle_id' => (string) $beneficiary->getAuthIdentifier(),
            'phone' => $beneficiary->phoneNumber(),
            'name' => $beneficiary->displayName(),
            'employee_number' => $beneficiary->employeeNumber(),
        ];
    }

    public function search(?string $term = null, int $limit = 25): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        $table = config('municipality.oracle.drivers_table');
        $columns = config('municipality.oracle.driver_columns');

        return Beneficiary::query()
            ->from($table)
            ->selectRaw(sprintf(
                '%s as oracle_id, %s as phone, %s as name, %s as employee_number',
                $columns['id'],
                $columns['phone'],
                $columns['name'],
                $columns['employee_number'],
            ))
            ->when($term, function ($query) use ($term, $columns): void {
                $query->where(function ($nested) use ($term, $columns): void {
                    $nested->where($columns['name'], 'like', "%{$term}%")
                        ->orWhere($columns['phone'], 'like', "%{$term}%")
                        ->orWhere($columns['employee_number'], 'like', "%{$term}%");
                });
            })
            ->limit($limit)
            ->get()
            ->map(fn (Beneficiary $beneficiary): array => [
                'oracle_id' => (string) $beneficiary->getAuthIdentifier(),
                'phone' => $beneficiary->phoneNumber(),
                'name' => $beneficiary->displayName(),
                'employee_number' => $beneficiary->employeeNumber(),
            ]);
    }

    public function findManyByIds(array $ids): Collection
    {
        $ids = collect($ids)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->readiness->available()) {
            return collect();
        }

        $columns = config('municipality.oracle.driver_columns');

        return Beneficiary::query()
            ->whereIn($columns['id'], $ids->all())
            ->get()
            ->map(fn (Beneficiary $beneficiary): array => [
                'oracle_id' => (string) $beneficiary->getAuthIdentifier(),
                'phone' => $beneficiary->phoneNumber(),
                'name' => $beneficiary->displayName(),
                'employee_number' => $beneficiary->employeeNumber(),
            ])
            ->keyBy('oracle_id');
    }

    public function count(?string $term = null): int
    {
        return $this->search($term, 1000)->count();
    }
}
