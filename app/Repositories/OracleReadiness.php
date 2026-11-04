<?php

namespace App\Repositories;

class OracleReadiness
{
    public function available(): bool
    {
        $connection = config('database.connections.oracle', []);

        return extension_loaded('oci8')
            && (
                filled($connection['tns'] ?? null)
                || filled($connection['service_name'] ?? null)
                || (filled($connection['host'] ?? null) && filled($connection['database'] ?? null))
            );
    }
}
