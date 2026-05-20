<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LogicException;

class MogSsoUser extends Authenticatable
{
    protected $connection = 'oracle';

    public $timestamps = false;

    protected $guarded = ['*'];

    public function getTable(): string
    {
        return 'mog_users.users_list';
    }

    public function getAuthIdentifierName(): string
    {
        return env('MOG_SSO_USERS_KEY_COLUMN', 'ID');
    }

    public function getKeyName(): string
    {
        return $this->getAuthIdentifierName();
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('MOG_USERS.USERS is a read-only SSO source.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('MOG_USERS.USERS is a read-only SSO source.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('MOG_USERS.USERS is a read-only SSO source.');
    }

    public function fill(array $attributes): static
    {
        if ($attributes !== []) {
            throw new MassAssignmentException('MOG_USERS.USERS is read-only.');
        }

        return $this;
    }

    protected function performInsert(Builder $query): bool
    {
        throw new LogicException('MOG_USERS.USERS is a read-only SSO source.');
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('MOG_USERS.USERS is a read-only SSO source.');
    }
}
