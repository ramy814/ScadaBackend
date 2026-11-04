<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LogicException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class MogSsoUser extends Authenticatable
{
	use HasFactory, Notifiable;
	
    protected $connection = 'oracle';
	protected $table = 'mog_users.users_list';

	protected $fillable = [
        'name',
        'email',
        'password',
    ];
	
	protected $hidden = [
        'password',
        'remember_token',
    ];
	
	protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
	
	public function getRememberTokenName()
    {
        return null;
    }
	
}
