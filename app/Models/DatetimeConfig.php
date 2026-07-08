<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatetimeConfig extends Model
{
    protected $table = 'datetime_config';

    // Single-row config table — always id = 1.
    public $timestamps = false;

    // Primary key is a tiny integer, not auto-incrementing.
    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'int';

    protected $fillable = [
        'mode',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'last_triggered_at',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'last_triggered_at' => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * Always return the single config row (id = 1).
     * Creates the row if it somehow does not exist.
     */
    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], ['mode' => 'automatic']);
    }
}
