<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

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
    ];

    protected $casts = [
        'time_in'  => 'datetime',
        'time_out' => 'datetime',
        'date'     => 'date',
    ];
}
