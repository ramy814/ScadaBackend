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
        'id' => 'OID',
        'phone' => 'MOBILE_NUM',
        'name' => 'FULL_NAME',
        'employee_number' => 'NATIONAL_ID',
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
            ->first();

        if (! $beneficiary) {
            Log::warning('Driver beneficiary phone lookup failed.', [
                'drivers_table' => self::DRIVERS_TABLE,
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
        $columns = self::DRIVER_COLUMNS;
        $terms = $this->phoneLookupTerms($phone);

        $lastNineDigits = $terms['last_nine_digits'];
        $exactMatches = DB::connection('oracle')
            ->table(self::DRIVERS_TABLE)
            ->whereIn($columns['phone'], $terms['variations'])
            ->count();

        $cleanMatches = DB::connection('oracle')
            ->table(self::DRIVERS_TABLE)
            ->whereIn(DB::raw($terms['normalized_column']), $terms['clean_variations'])
            ->count();

        $lastNineMatches = 0;
        $sample = null;

        if ($lastNineDigits) {
            $lastNineQuery = DB::connection('oracle')
                ->table(self::DRIVERS_TABLE)
                ->whereRaw(
                    "SUBSTR(REGEXP_REPLACE({$columns['phone']}, '[^0-9]', ''), -9) = ?",
                    [$lastNineDigits],
                );

            $lastNineMatches = (clone $lastNineQuery)->count();
            $sample = (clone $lastNineQuery)
                ->select($columns['id'], $columns['phone'], $columns['name'])
                ->first();
        }

        return [
            'code_version' => 'driver-phone-lookup-last9-2026-05-31',
            'drivers_table' => self::DRIVERS_TABLE,
            'phone_column' => $columns['phone'],
            'id_column' => $columns['id'],
            'input_phone' => $phone,
            'clean_phone' => $terms['clean'],
            'last_nine_digits' => $lastNineDigits,
            'variations' => $terms['variations'],
            'clean_variations' => $terms['clean_variations'],
            'exact_matches' => $exactMatches,
            'clean_matches' => $cleanMatches,
            'last_nine_matches' => $lastNineMatches,
            'sample' => $sample ? [
                'id' => (string) ($sample->{$columns['id']} ?? ''),
                'phone' => $this->maskPhone((string) ($sample->{$columns['phone']} ?? '')),
                'name_present' => filled($sample->{$columns['name']} ?? null),
            ] : null,
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

    private function phoneLookupTerms(string $phone): array
    {
        $columns = self::DRIVER_COLUMNS;

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

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return '****';
        }

        return str_repeat('*', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }
}
