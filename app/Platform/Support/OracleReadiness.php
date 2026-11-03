<?php

declare(strict_types=1);

namespace App\Platform\Support;

/**
 * Same contract as the legacy OracleReadiness: callers fail open/closed when the
 * shared Oracle connection is not configured. The local SQLite fixture used by
 * tests and the demo counts as available.
 */
class OracleReadiness
{
    public function available(): bool
    {
        $connection = config('database.connections.oracle', []);

        if (($connection['driver'] ?? 'oracle') !== 'oracle') {
            return true;
        }

        return extension_loaded('oci8')
            && (
                filled($connection['tns'] ?? null)
                || filled($connection['service_name'] ?? null)
                || (filled($connection['host'] ?? null) && filled($connection['database'] ?? null))
            );
    }
}
