<?php

namespace App\Repositories;

class OracleReadiness
{
    public function available(): bool
    {
        return extension_loaded('oci8') && filled(config('database.connections.oracle.service_name'));
    }
}

