<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    // The original users table has created_at but MySQL handles updated_at
    // via ON UPDATE CURRENT_TIMESTAMP — Laravel must not try to write it.
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'role',
        'first_name',
        'last_name',
        'email',
        'profile_pic',
    ];

    protected $hidden = [
        // Don't hide password — Hash::check() in AuthController needs to read it
    ];

    protected $casts = [
        // Do NOT cast password as 'hashed' — that auto-hashes on set,
        // but also interferes with Hash::check() reads in some Laravel versions.
    ];
}
