<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RfidCard Model
 *
 * Represents a registered student/staff card in the rfid_cards table.
 * A card must exist here before the RFID kiosk will accept that ID —
 * unknown IDs return a 404 "not registered" error from the scan endpoint.
 *
 * is_active allows an admin to temporarily disable a card (e.g. a lost
 * or suspended ID) without deleting the registration entirely.
 *
 * Note: $timestamps = false — this table uses a single registered_at
 * column (set by a MySQL DEFAULT CURRENT_TIMESTAMP) rather than
 * Laravel's two-column timestamp convention.
 */
class RfidCard extends Model
{
    /** The database table for registered RFID cards. */
    protected $table = 'rfid_cards';

    /**
     * Disable automatic timestamp management.
     * registered_at is set by MySQL on INSERT and is read-only from PHP.
     */
    public $timestamps = false;

    /**
     * Mass-assignable columns.
     * registered_at is intentionally excluded — MySQL sets it automatically.
     */
    protected $fillable = [
        'id_number',       // normalised school ID (uppercase, no spaces)
        'last_name',
        'first_name',
        'middle_initial',
        'is_active',       // 1 = card accepted at kiosk, 0 = card rejected
    ];

    /**
     * Column type casts.
     * is_active is cast to boolean so PHP code can use true/false naturally.
     * registered_at is cast to a Carbon datetime for display formatting.
     */
    protected $casts = [
        'is_active'     => 'boolean',
        'registered_at' => 'datetime',
    ];
}
