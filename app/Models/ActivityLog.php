<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ActivityLog Model
 *
 * Represents a single audit log entry in the activity_logs table.
 * Every significant action in the system (login, adding records, changing
 * settings, etc.) is written here by ActivityLogger::log().
 *
 * Logs are append-only from the application's perspective — they are
 * only deleted manually by an admin via the Settings > Activity Logs panel
 * (clear all, bulk delete, or archive old entries).
 *
 * Note: $timestamps = false — this table only has a created_at column,
 * not updated_at. The CREATED_AT constant tells Laravel which column to
 * use for the creation timestamp.
 */
class ActivityLog extends Model
{
    /** The database table for audit log entries. */
    protected $table = 'activity_logs';

    /**
     * Disable automatic dual-timestamp management.
     * Only created_at exists on this table.
     */
    public $timestamps = false;

    /**
     * Tell Laravel which column holds the creation timestamp.
     * Required when $timestamps = false but we still want Carbon casting.
     */
    const CREATED_AT = 'created_at';

    /**
     * Mass-assignable columns — set by ActivityLogger::log() on every write.
     */
    protected $fillable = [
        'user_id',      // FK to users.id — who performed the action
        'username',     // denormalised username for fast display (no JOIN needed)
        'action',       // uppercase action code e.g. 'LOGIN', 'EDIT_ATTENDANCE'
        'target',       // the table/resource being acted on e.g. 'attendance'
        'description',  // human-readable sentence describing the action
        'remarks',      // optional supplementary detail
        'ip_address',   // client IP at the time of the action
    ];

    /**
     * Column type casts.
     * created_at is cast to a Carbon datetime so it formats automatically
     * in JSON responses and the Settings > Activity Logs table.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];
}
