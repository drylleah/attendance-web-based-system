<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TimeRecord Model
 *
 * Represents a row in the permanent "time_records" archive table.
 * Records arrive here in two ways:
 *   1. Bulk-copied from the attendance table when the admin clicks
 *      "Save to Time Record" (TimeRecordController::save).
 *   2. Manually inserted via the Time Records page "New Entry" modal
 *      (TimeRecordController::store).
 *
 * Unlike the Attendance model, these records are considered permanent
 * and are the historical source of truth for attendance data.
 *
 * Note: $timestamps = false — this table uses its own saved_at column
 * to record when records were archived, not Laravel's standard timestamps.
 */
class TimeRecord extends Model
{
    /** The database table for archived time records. */
    protected $table = 'time_records';

    /**
     * Disable automatic created_at / updated_at management.
     * The saved_at column serves as the archive timestamp.
     */
    public $timestamps = false;

    /**
     * Mass-assignable columns.
     */
    protected $fillable = [
        'id_number',       // student/staff ID
        'last_name',
        'first_name',
        'middle_initial',
        'time_in',         // datetime of time-in event
        'time_out',        // datetime of time-out event (may be null)
        'date',            // date of the record (YYYY-MM-DD)
        'remarks',         // optional notes
        'saved_at',        // when this row was moved/added to the archive
    ];

    /**
     * Column type casts.
     * Datetime columns are cast to Carbon instances for easy formatting.
     */
    protected $casts = [
        'time_in'  => 'datetime',
        'time_out' => 'datetime',
        'date'     => 'date',
        'saved_at' => 'datetime',
    ];
}
