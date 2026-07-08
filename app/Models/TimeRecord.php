<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeRecord extends Model
{
    protected $table = 'time_records';

    public $timestamps = false;

    protected $fillable = [
        'id_number',
        'last_name',
        'first_name',
        'middle_initial',
        'time_in',
        'time_out',
        'date',
        'remarks',
        'saved_at',
    ];

    protected $casts = [
        'time_in'  => 'datetime',
        'time_out' => 'datetime',
        'date'     => 'date',
        'saved_at' => 'datetime',
    ];
}
