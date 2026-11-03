<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use App\Modules\Fleet\Models\Beneficiary;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Wells\Models\Inspector;

/**
 * Mobile app principals, identical to the legacy EnsureMobilePrincipal mapping.
 *
 * Sanctum tokens in Oracle store the legacy class names in TOKENABLE_TYPE. The morph
 * map resolves them to the platform models and makes new tokens use the same names,
 * so sessions stay valid in both directions during the cutover (and after a rollback).
 * Only the types the legacy backend can resolve are mapped; older names it cannot load
 * (App\Models\User, App\Models\DriverProfile, App\Models\Inspector) stay rejected.
 */
final class MobilePrincipals
{
    /** @var array<string, class-string> ability => principal model */
    public const BY_ABILITY = [
        'driver' => Beneficiary::class,
        'wells' => Inspector::class,
        'scada' => ValveInspector::class,
    ];

    /** @var array<string, class-string> legacy TOKENABLE_TYPE => platform model */
    public const LEGACY_MORPH_TYPES = [
        'App\\Models\\Beneficiary' => Beneficiary::class,
        'App\\Models\\Wells\\Inspector' => Inspector::class,
        'App\\Models\\Valves\\ValveInspector' => ValveInspector::class,
    ];
}
