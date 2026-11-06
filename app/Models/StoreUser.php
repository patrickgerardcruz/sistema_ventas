<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class StoreUser extends Authenticatable
{
    protected $table = 'users';

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean'];
    }
}
