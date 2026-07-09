<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * ActivityLogger
 *
 * A thin static service that every controller uses to write audit log
 * entries to the activity_logs table.  Centralising the logic here
 * means all log entries share a consistent format and the try/catch
 * safety wrapper only needs to exist in one place.
 *
 * Design principle: logging must NEVER crash a live request.
 * All exceptions are caught and forwarded to Laravel's own logger
 * so they are visible in storage/logs/laravel.log without bubbling
 * up to the user as a 500 error.
 *
 * Usage:
 *   ActivityLogger::log($request, 'LOGIN',          'users',      'User "admin" logged in');
 *   ActivityLogger::log($request, 'ADD_ATTENDANCE', 'attendance', 'Added record for …', 'optional remarks');
 */
class ActivityLogger
{
    /**
     * Write a single activity log entry.
     *
     * @param  Request      $request      The current HTTP request (used to read session + IP)
     * @param  string       $action       A short uppercase action code, e.g. 'LOGIN', 'EDIT_ATTENDANCE'
     * @param  string|null  $target       The table or resource being acted on, e.g. 'attendance'
     * @param  string|null  $description  A human-readable sentence describing what happened
     * @param  string|null  $remarks      Optional extra context (e.g. field-level notes)
     */
    public static function log(
        Request $request,
        string  $action,
        ?string $target      = null,
        ?string $description = null,
        ?string $remarks     = null
    ): void {
        try {
            // Read the logged-in user's identity from the session
            // The "unknown" fallback covers the edge case of logging
            // before the session has been fully established (e.g. failed login)
            $userId   = $request->session()->get('userId');
            $username = $request->session()->get('username', 'unknown');

            // Determine the real client IP address.
            // X-Forwarded-For is checked first to support reverse-proxy
            // and load-balancer environments; we take only the first
            // (client-facing) IP from the potentially comma-separated list.
            $ip = $request->header('X-Forwarded-For')
                ? explode(',', $request->header('X-Forwarded-For'))[0]
                : $request->ip();

            // Insert the log row — timestamps are handled by the model's CREATED_AT constant
            ActivityLog::create([
                'user_id'     => $userId,
                'username'    => $username,
                'action'      => $action,
                'target'      => $target,
                'description' => $description,
                'remarks'     => $remarks,
                'ip_address'  => trim($ip), // trim any whitespace from the forwarded header
            ]);
        } catch (\Throwable $e) {
            // Forward the error to the standard Laravel log (storage/logs/laravel.log)
            // but never let a logging failure propagate to the user
            \Illuminate\Support\Facades\Log::error('ActivityLogger error: ' . $e->getMessage());
        }
    }
}
