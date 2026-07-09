<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DatetimeConfig Model
 *
 * Stores the single global Date & Time configuration row (id = 1).
 * This table is a "singleton" — it always contains exactly one row,
 * created by the database seeder and never deleted.
 *
 * Two operating modes are supported:
 *
 *   automatic — The system uses the real current date/time for all
 *               operations.  This is the default.
 *
 *   manual    — The admin sets a start and end datetime window.
 *               The Dashboard polls every 15 seconds and automatically
 *               triggers "Save to Time Record" once the end datetime
 *               is reached (last_triggered_at is set to prevent a
 *               second trigger on the same window).
 *
 * Note: $timestamps = false and $incrementing = false because id is
 * always 1 and is set manually, not auto-incremented.
 */
class DatetimeConfig extends Model
{
    /** The database table (single-row config). */
    protected $table = 'datetime_config';

    /**
     * Disable automatic timestamp management.
     * updated_at is handled by a MySQL ON UPDATE trigger.
     */
    public $timestamps = false;

    /**
     * The primary key is always 1 and is set explicitly — not auto-incremented.
     */
    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'int';

    /**
     * Mass-assignable columns.
     */
    protected $fillable = [
        'mode',               // 'automatic' or 'manual'
        'start_date',         // start of the manual attendance window (YYYY-MM-DD)
        'start_time',         // start time of the window (HH:MM:SS)
        'end_date',           // end of the manual window (YYYY-MM-DD)
        'end_time',           // end time of the window (HH:MM:SS)
        'last_triggered_at',  // set when the scheduled auto-save fires — prevents re-triggering
    ];

    /**
     * Column type casts.
     * Dates are cast to Carbon date instances; datetimes to Carbon datetime instances.
     */
    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'last_triggered_at' => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * instance()
     *
     * Retrieves the single config row (id = 1).
     * If the row somehow does not exist (e.g. after a partial migration),
     * it is created with safe defaults so the system never crashes on a
     * missing config.
     *
     * All code that needs the config should call this method rather than
     * using DatetimeConfig::find(1) directly, to benefit from the
     * self-healing firstOrCreate behaviour.
     *
     * @return static  The singleton config row
     */
    public static function instance(): static
    {
        // firstOrCreate: return the existing row, or create it with
        // mode='automatic' if it was somehow absent
        return static::firstOrCreate(['id' => 1], ['mode' => 'automatic']);
    }
}
