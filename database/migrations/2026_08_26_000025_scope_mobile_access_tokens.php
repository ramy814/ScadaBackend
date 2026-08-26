<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Includes legacy model names so installed app versions keep their current sessions.
     *
     * @var array<string, string>
     */
    private const ABILITIES_BY_TOKENABLE_TYPE = [
        'App\\Models\\Beneficiary' => 'driver',
        'App\\Models\\User' => 'driver',
        'App\\Models\\DriverProfile' => 'driver',
        'App\\Models\\Wells\\Inspector' => 'wells',
        'App\\Models\\Inspector' => 'wells',
        'App\\Models\\Valves\\ValveInspector' => 'scada',
    ];

    public function up(): void
    {
        foreach (self::ABILITIES_BY_TOKENABLE_TYPE as $type => $ability) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $type)
                ->update(['abilities' => json_encode([$ability], JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        DB::table('personal_access_tokens')
            ->whereIn('tokenable_type', array_keys(self::ABILITIES_BY_TOKENABLE_TYPE))
            ->update(['abilities' => '["*"]']);
    }
};
