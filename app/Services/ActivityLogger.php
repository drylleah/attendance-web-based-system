<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * ActivityLogger — Laravel equivalent of src/logger.js
 *
 * Usage:
 *   ActivityLogger::log($request, 'LOGIN', 'users', 'User "admin" logged in');
 *   ActivityLogger::log($request, 'ADD_ATTENDANCE', 'attendance', 'Added record for …', 'optional remarks');
 */
class ActivityLogger
{
    /**
     * Write one activity log entry. Never throws — logging must never
     * crash a live request (mirrors the original logger.js try/catch behaviour).
     */
    public static function log(
        Request $request,
        string  $action,
        ?string $target      = null,
        ?string $description = null,
        ?string $remarks     = null
    ): void {
        try {
            $userId   = $request->session()->get('userId');
            $username = $request->session()->get('username', 'unknown');

            // Respect X-Forwarded-For for proxied environments (same logic as logger.js)
            $ip = $request->header('X-Forwarded-For')
                ? explode(',', $request->header('X-Forwarded-For'))[0]
                : $request->ip();

            ActivityLog::create([
                'user_id'     => $userId,
                'username'    => $username,
                'action'      => $action,
                'target'      => $target,
                'description' => $description,
                'remarks'     => $remarks,
                'ip_address'  => trim($ip),
            ]);
        } catch (\Throwable $e) {
            // Log to Laravel's own logger but never propagate the error
            \Illuminate\Support\Facades\Log::error('ActivityLogger error: ' . $e->getMessage());
        }
    }
}
