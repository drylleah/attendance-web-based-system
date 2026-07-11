<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Attendance Model
 *
 * Represents a row in the "live" attendance table — records that have
 * been scanned via RFID or manually entered but have NOT yet been moved
 * to the permanent time_records archive.
 *
 * Lifecycle:
 *   1. Created when a student taps in (RfidController::scan) or an admin
 *      adds a record manually (AttendanceController::store).
 *   2. Updated when the same student taps out — the time_out column is filled.
 *   3. Bulk-moved to time_records and deleted when the admin clicks
 *      "Save to Time Record" (TimeRecordController::save).
 *
 * Note: $timestamps = false because this table uses explicit date/time
 * columns (time_in, time_out, date) instead of Laravel's created_at/updated_at.
 */
class Attendance extends Model
{
    /** The database table for live attendance records. */
    protected $table = 'attendance';

    /**
     * Disable automatic created_at / updated_at management.
     * This table uses time_in, time_out, and date instead.
     */
    public $timestamps = false;

    /**
     * Mass-assignable columns.
     * All other columns default to guarded.
     */
    protected $fillable = [
        'id_number',      // student/staff ID from the RFID card
        'last_name',
        'first_name',
        'middle_initial',
        'time_in',        // datetime when the student tapped in
        'time_out',       // datetime when the student tapped out (null if still in)
        'date',           // date of the attendance record (YYYY-MM-DD)
        'remarks',        // optional admin notes
    ];

    /**
     * Column type casts.
     * time_in and time_out are cast to Carbon datetime instances for
     * easy formatting in controllers and API responses.
     * date is cast to a Carbon date instance.
     */
    protected $casts = [
        'time_in'  => 'datetime',
        'time_out' => 'datetime',
        'date'     => 'string',
    ];
}
