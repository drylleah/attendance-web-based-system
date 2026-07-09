<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User Model
 *
 * Represents the single admin account that can log into the system.
 * Extends Authenticatable so it can integrate with Laravel's auth
 * helpers if needed in the future, though the current system uses
 * manual session-based auth in AuthController.
 *
 * Notable decisions:
 * - $timestamps = false  — the migration uses MySQL's ON UPDATE
 *   CURRENT_TIMESTAMP for updated_at; letting Laravel manage it
 *   would cause conflicts with that trigger.
 * - password is NOT in $hidden and NOT cast as 'hashed' — we call
 *   Hash::check() directly in AuthController, and the 'hashed' cast
 *   in some Laravel versions interferes with reading the stored hash.
 * - profile_pic stores a base64-encoded data URI in a MEDIUMTEXT column.
 */
class User extends Authenticatable
{
    use Notifiable;

    /** The database table this model reads/writes. */
    protected $table = 'users';

    /**
     * Disable automatic timestamp management.
     * The migration handles updated_at via a MySQL trigger.
     */
    public $timestamps = false;

    /**
     * Fields that are mass-assignable (used by User::create() and update()).
     * All other columns are guarded against mass assignment by default.
     */
    protected $fillable = [
        'username',
        'password',
        'role',
        'first_name',
        'last_name',
        'email',
        'profile_pic', // base64 data URI, stored in MEDIUMTEXT
    ];

    /**
     * Fields hidden from array/JSON serialisation.
     * Password is intentionally NOT hidden here because AuthController
     * needs to call Hash::check() against the stored hash.
     */
    protected $hidden = [];

    /**
     * No column casts are applied.
     * In particular, password must NOT be cast as 'hashed' because that
     * would auto-hash on assignment, which conflicts with Hash::check()
     * reads in certain Laravel versions.
     */
    protected $casts = [];
}
