<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of mobile Sanctum tokens issued by the legacy backend. The platform
 * never issues or revokes them; it only counts them (e.g. deletion blockers).
 */
class MobileAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    public $timestamps = false;
}
