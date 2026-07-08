<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfidCard extends Model
{
    protected $table = 'rfid_cards';

    // No updated_at column — only registered_at
    public $timestamps = false;

    protected $fillable = [
        'id_number',
        'last_name',
        'first_name',
        'middle_initial',
        'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'registered_at' => 'datetime',
    ];
}
