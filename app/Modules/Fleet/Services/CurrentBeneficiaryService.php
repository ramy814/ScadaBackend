<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Beneficiary;
use Illuminate\Contracts\Auth\Authenticatable;

class CurrentBeneficiaryService
{
    public function resolve(?Authenticatable $user): Beneficiary
    {
        abort_unless($user instanceof Beneficiary, 401, 'غير مصرح.');

        return $user;
    }
}
