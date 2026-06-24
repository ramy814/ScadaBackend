<?php

namespace App\Repositories;

use App\Models\Beneficiary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MunicipalityDriverRepository
{
    public function __construct(private readonly OracleReadiness $readiness) {}

    public function findBeneficiaryByPhone(string $phone): ?Beneficiary
    {
        if (! $this->readiness->available()) {
            throw new \RuntimeException('Oracle database connection is not ready or configured. Cannot check beneficiary table directly.');
        }

        $columns = $this->driverColumns();

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

        $lastNineDigits = strlen(preg_replace('/\D+/', '', $clean) ?? '') >= 9
            ? substr(preg_replace('/\D+/', '', $clean), -9)
            : null;

        $beneficiary = Beneficiary::query()
            ->where(function ($query) use ($columns, $variations, $cleanVariations, $lastNineDigits, $normalizedColumn): void {
                $query->whereIn($columns['phone'], $variations)
                    ->orWhereIn(DB::raw($normalizedColumn), $cleanVariations);

                if ($lastNineDigits) {
                    $query->orWhereRaw(
                        "SUBSTR(REGEXP_REPLACE({$columns['phone']}, '[^0-9]', ''), -9) = ?",
                        [$lastNineDigits],
                    );
                }
            })
            ->when(filled($columns['active'] ?? null), fn ($q) => $q->where($columns['active'], 1))
            ->first();

        if (! $beneficiary) {
            Log::warning('Driver beneficiary phone lookup failed.', [
                'drivers_table' => $this->driversTable(),
                'phone_column' => $columns['phone'],
                'id_column' => $columns['id'] ?? null,
                'clean_phone' => $clean,
                'last_nine_digits' => $lastNineDigits,
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

    public function debugPhoneLookup(string $phone): array
    {
        $columns = $this->driverColumns();
        $terms = $this->phoneLookupTerms($phone);

        return [
            'code_version' => 'driver-phone-lookup-last9-2026-05-31',
            'drivers_table' => $this->driversTable(),
            'phone_column' => $columns['phone'],
            'id_column' => $columns['id'],
            'input_phone' => $phone,
            'clean_phone' => $terms['clean'],
            'last_nine_digits' => $terms['last_nine_digits'],
            'variations' => $terms['variations'],
            'clean_variations' => $terms['clean_variations'],
        ];
    }

    public function search(?string $term = null, int $limit = 25): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        $table = $this->driversTable();
        $columns = $this->driverColumns();

        try {
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
        } catch (QueryException $e) {
            $this->logOracleReadFailure('search', $e);

            return collect();
        }
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
            return collect();
        }

        $columns = $this->driverColumns();

        try {
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
        } catch (QueryException $e) {
            $this->logOracleReadFailure('find_many_by_ids', $e);

            return collect();
        }
    }

    public function count(?string $term = null): int
    {
        if (! $this->readiness->available()) {
            return 0;
        }

        $table = $this->driversTable();
        $columns = $this->driverColumns();

        try {
            return (int) DB::connection('oracle')
                ->table($table)
                ->when(filled($columns['active'] ?? null), fn ($query) => $query->where($columns['active'], 1))
                ->when($term, function ($query) use ($term, $columns): void {
                    $query->where(function ($nested) use ($term, $columns): void {
                        $nested->where($columns['name'], 'like', "%{$term}%")
                            ->orWhere($columns['phone'], 'like', "%{$term}%")
                            ->orWhere($columns['employee_number'], 'like', "%{$term}%");
                    });
                })
                ->count();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('count_active', $e);

            return 0;
        }
    }

    private function logOracleReadFailure(string $operation, QueryException $e): void
    {
        Log::warning('Municipality driver Oracle read failed.', [
            'operation' => $operation,
            'drivers_table' => $this->driversTable(),
            'error_code' => $e->getCode(),
            'error' => $e->getMessage(),
        ]);
    }

    private function phoneLookupTerms(string $phone): array
    {
        $columns = $this->driverColumns();

        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $westernDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalized = str_replace($arabicDigits, $westernDigits, $phone);
        $clean = preg_replace('/[^0-9+]/', '', $normalized);

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

        $digitsOnly = preg_replace('/\D+/', '', $clean) ?? '';

        return [
            'clean' => $clean,
            'variations' => $variations,
            'clean_variations' => $cleanVariations,
            'last_nine_digits' => strlen($digitsOnly) >= 9 ? substr($digitsOnly, -9) : null,
            'normalized_column' => sprintf(
                "REGEXP_REPLACE(TRANSLATE(%s, '٠١٢٣٤٥٦٧٨٩', '0123456789'), '[^0-9+]', '')",
                $columns['phone'],
            ),
        ];
    }

    private function driversTable(): string
    {
        return (string) config('fleet.oracle.drivers_table', 'BENEFICIARIES');
    }

    private function driverColumns(): array
    {
        return (array) config('fleet.oracle.driver_columns', []);
    }

}
