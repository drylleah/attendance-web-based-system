<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IncidentReport Model
 *
 * Represents a formal incident report filed by an admin against a student.
 * Reports progress through a simple workflow:
 *   open → under_review → resolved | dismissed
 *
 * The reporter identity (reported_by, reporter_name) is recorded at
 * creation time from the session, not the request body, so it cannot
 * be spoofed by the client.
 *
 * Note: $timestamps = false — Laravel's dual-timestamp convention is not
 * used here; created_at is set automatically by MySQL on INSERT.
 */
class IncidentReport extends Model
{
    /** The database table for incident reports. */
    protected $table = 'incident_reports';

    /**
     * Disable automatic timestamp management.
     * created_at is set by MySQL; updated_at is not tracked for reports.
     */
    public $timestamps = false;

    /**
     * Mass-assignable columns.
     */
    protected $fillable = [
        'reported_by',    // FK to users.id — the admin who filed the report
        'reporter_name',  // denormalised username for display without a JOIN
        'subject_id_no',  // the student's ID number (may be null)
        'subject_name',   // the student's full name
        'incident_date',  // when the incident occurred (not necessarily today)
        'incident_type',  // category: General | Attendance Fraud | Suspicious Activity | etc.
        'description',    // full narrative of the incident
        'status',         // workflow state: open | under_review | resolved | dismissed
        'remarks',        // admin notes added during review
    ];

    /**
     * Column type casts.
     * incident_date is cast to a Carbon date instance for easy formatting.
     */
    protected $casts = [
        'incident_date' => 'date',
    ];
}
