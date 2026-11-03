<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** MOG SSO identity (MOG_USERS.USERS_LIST). `id` is the SSO id, never `emp_id`. */
class MogSsoUser extends Authenticatable
{
    protected $connection = 'oracle';

    protected $table = 'mog_users.users_list';

    public $timestamps = false;

    protected $hidden = ['password', 'remember_token'];

    public function getRememberTokenName()
    {
        return null;
    }

    public function displayName(): string
    {
        return (string) ($this->full_name ?? $this->name ?? ('#'.$this->getKey()));
    }
}
