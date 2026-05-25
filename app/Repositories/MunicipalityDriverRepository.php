<?php

namespace App\Repositories;

use App\Models\Beneficiary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MunicipalityDriverRepository
{
    private const DRIVERS_TABLE = 'BENEFICIARIES';

    private const DRIVER_COLUMNS = [
        'id' => 'ID',
        'phone' => 'MOBILE',
        'name' => 'NAME',
        'employee_number' => 'EMP_NO',
    ];

    public function __construct(private readonly OracleReadiness $readiness) {}

    public function findBeneficiaryByPhone(string $phone): ?Beneficiary
    {
        if (! $this->readiness->available()) {
            throw new \RuntimeException('Oracle database connection is not ready or configured. Cannot check beneficiary table directly.');
        }

        $columns = self::DRIVER_COLUMNS;

        // Convert Eastern Arabic numerals to Western Arabic numerals
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $westernDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalized = str_replace($arabicDigits, $westernDigits, $phone);

        // Strip everything except digits and plus sign
        $clean = preg_replace('/[^0-9+]/', '', $normalized);

        // Try to identify Palestinian mobile pattern (Jawwal 59xxxxxxx or Ooredoo 56xxxxxxx)
        $national = null;
        if (preg_match('/^(?:\+?970|00970|0)?(5[69]\d{7})$/', $clean, $matches)) {
            $national = $matches[1];
        }

        $variations = [$phone, $normalized, $clean];
        if ($national) {
            $variations[] = '0' . $national;
            $variations[] = $national;
            $variations[] = '970' . $national;
            $variations[] = '9700' . $national;
            $variations[] = '+970' . $national;
            $variations[] = '+9700' . $national;
            $variations[] = '00970' . $national;
            $variations[] = '009700' . $national;
        }

        $variations = array_values(array_unique(array_filter($variations)));
        $cleanVariations = array_values(array_unique(array_filter(array_map(
            fn (string $value): string => preg_replace(
                '/[^0-9+]/',
                '',
                str_replace($arabicDigits, $westernDigits, $value),
            ),
            $variations,
        ))));

        $normalizedColumn = sprintf(
            "REGEXP_REPLACE(TRANSLATE(%s, '٠١٢٣٤٥٦٧٨٩', '0123456789'), '[^0-9+]', '')",
            $columns['phone'],
        );

        $beneficiary = Beneficiary::query()
            ->where(function ($query) use ($columns, $variations, $cleanVariations, $normalizedColumn): void {
                $query->whereIn($columns['phone'], $variations)
                    ->orWhereIn(DB::raw($normalizedColumn), $cleanVariations);
            })
            ->first();

        if (! $beneficiary) {
            Log::warning('Driver beneficiary phone lookup failed.', [
                'drivers_table' => self::DRIVERS_TABLE,
                'phone_column' => $columns['phone'],
                'id_column' => $columns['id'] ?? null,
                'clean_phone' => $clean,
                'variations' => $variations,
                'clean_variations' => $cleanVariations,
            ]);
        }

        return $beneficiary;
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
            throw new \RuntimeException('Oracle database connection is not ready or configured. Cannot search beneficiaries directly.');
        }

        $table = self::DRIVERS_TABLE;
        $columns = self::DRIVER_COLUMNS;

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

        if ($ids->isEmpty()) {
            return collect();
        }

        if (! $this->readiness->available()) {
            throw new \RuntimeException('Oracle database connection is not ready or configured. Cannot check beneficiaries directly.');
        }

        $columns = self::DRIVER_COLUMNS;

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
