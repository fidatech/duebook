<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A system administrator of DueBook: signs in at /admin and never sees inside a business. */
class Admin extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function initials(): string
    {
        return User::initialsOf($this->name);
    }
}
