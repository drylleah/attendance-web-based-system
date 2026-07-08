<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DatetimeConfig;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * SettingsController — equivalent of src/routes/settings.js
 *
 * GET  /api/settings/profile
 * PUT  /api/settings/profile
 * PUT  /api/settings/avatar
 * PUT  /api/settings/password
 * GET  /api/settings/datetime
 * PUT  /api/settings/datetime
 * PUT  /api/settings/datetime/triggered
 * GET  /api/settings/activity-logs
 * DELETE /api/settings/activity-logs
 * POST /api/settings/activity-logs/bulk-delete
 * POST /api/settings/activity-logs/archive
 * GET  /api/settings/activity-logs/export
 */
class SettingsController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/settings/profile
    // ---------------------------------------------------------------
    public function getProfile(Request $request): JsonResponse
    {
        $user = User::select('username', 'first_name', 'last_name', 'email', 'profile_pic')
                    ->find($request->session()->get('userId'));

        return response()->json($user ?? (object) []);
    }

    // ---------------------------------------------------------------
    // PUT /api/settings/profile
    // ---------------------------------------------------------------
    public function updateProfile(Request $request): JsonResponse
    {
        $userId    = $request->session()->get('userId');
        $firstName = $request->input('first_name');
        $lastName  = $request->input('last_name');
        $email     = $request->input('email');

        User::where('id', $userId)->update([
            'first_name' => $firstName ?: null,
            'last_name'  => $lastName  ?: null,
            'email'      => $email     ?: null,
        ]);

        if ($firstName) {
            $request->session()->put('firstName', $firstName);
        }

        ActivityLogger::log(
            $request,
            'UPDATE_PROFILE',
            'users',
            "Updated profile — name: \"" . trim("{$firstName} {$lastName}") . "\", email: \"{$email}\""
        );

        return response()->json(['message' => 'Profile updated successfully.']);
    }

    // ---------------------------------------------------------------
    // PUT /api/settings/avatar  (base64 stored in MEDIUMTEXT)
    // ---------------------------------------------------------------
    public function updateAvatar(Request $request): JsonResponse
    {
        $userId     = $request->session()->get('userId');
        $profilePic = $request->input('profile_pic');

        User::where('id', $userId)->update(['profile_pic' => $profilePic ?: null]);

        ActivityLogger::log($request, 'UPDATE_AVATAR', 'users', 'Updated profile picture');

        return response()->json(['message' => 'Profile picture updated.']);
    }

    // ---------------------------------------------------------------
    // PUT /api/settings/password
    // ---------------------------------------------------------------
    public function changePassword(Request $request): JsonResponse
    {
        $userId      = $request->session()->get('userId');
        $currentPw   = $request->input('current_password', '');
        $newPw       = $request->input('new_password', '');

        if (! $currentPw || ! $newPw) {
            return response()->json(['error' => 'Both fields are required.'], 400);
        }
        if (strlen($newPw) < 6) {
            return response()->json(['error' => 'New password must be at least 6 characters.'], 400);
        }

        $user = User::find($userId);
        if (! $user || ! Hash::check($currentPw, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 400);
        }

        $user->update(['password' => Hash::make($newPw)]);

        ActivityLogger::log($request, 'CHANGE_PASSWORD', 'users', 'Changed account password');

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // ---------------------------------------------------------------
    // GET /api/settings/datetime
    // ---------------------------------------------------------------
    public function getDatetime(Request $request): JsonResponse
    {
        $cfg = DatetimeConfig::instance();

        return response()->json([
            'mode'               => $cfg->mode,
            'start_date'         => $cfg->start_date  ? $cfg->start_date->toDateString()  : null,
            'start_time'         => $cfg->start_time,
            'end_date'           => $cfg->end_date    ? $cfg->end_date->toDateString()    : null,
            'end_time'           => $cfg->end_time,
            'last_triggered_at'  => $cfg->last_triggered_at,
        ]);
    }

    // ---------------------------------------------------------------
    // PUT /api/settings/datetime
    // ---------------------------------------------------------------
    public function updateDatetime(Request $request): JsonResponse
    {
        $mode      = $request->input('mode');
        $startDate = $request->input('start_date');
        $startTime = $request->input('start_time');
        $endDate   = $request->input('end_date');
        $endTime   = $request->input('end_time');

        if (! in_array($mode, ['automatic', 'manual'])) {
            return response()->json(['error' => 'Invalid mode.'], 400);
        }
        if ($mode === 'manual' && (! $startDate || ! $startTime || ! $endDate || ! $endTime)) {
            return response()->json(
                ['error' => 'Start and End date/time are required for Manual mode.'], 400
            );
        }

        DatetimeConfig::where('id', 1)->update([
            'mode'               => $mode,
            'start_date'         => $startDate ?: null,
            'start_time'         => $startTime ?: null,
            'end_date'           => $endDate   ?: null,
            'end_time'           => $endTime   ?: null,
            'last_triggered_at'  => null,
        ]);

        $desc = $mode === 'manual'
            ? "Set Date/Time to Manual mode — start: {$startDate} {$startTime}, end: {$endDate} {$endTime}"
            : 'Set Date/Time to Automatic mode';

        ActivityLogger::log($request, 'UPDATE_DATETIME_CONFIG', 'datetime_config', $desc);

        return response()->json(['message' => 'Date and Time settings saved.']);
    }

    // ---------------------------------------------------------------
    // PUT /api/settings/datetime/triggered
    // ---------------------------------------------------------------
    public function markTriggered(Request $request): JsonResponse
    {
        DatetimeConfig::where('id', 1)->update(['last_triggered_at' => now()]);

        return response()->json(['message' => 'Marked as triggered.']);
    }

    // ---------------------------------------------------------------
    // GET /api/settings/activity-logs
    // ---------------------------------------------------------------
    public function getActivityLogs(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $action = $request->query('action');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = max(1, (int) $request->query('limit', 20));

        $query = ActivityLog::query();

        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('username',    'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('remarks',     'like', $like)
                  ->orWhere('ip_address',  'like', $like);
            });
        }
        if ($action) $query->where('action', $action);
        if ($from)   $query->whereDate('created_at', '>=', $from);
        if ($to)     $query->whereDate('created_at', '<=', $to);

        $total = $query->count();
        $logs  = $query->orderByDesc('created_at')
                       ->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->get();

        return response()->json([
            'logs'  => $logs,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // ---------------------------------------------------------------
    // DELETE /api/settings/activity-logs  (clear all)
    // ---------------------------------------------------------------
    public function clearActivityLogs(Request $request): JsonResponse
    {
        ActivityLog::query()->delete();
        ActivityLogger::log($request, 'CLEAR_ACTIVITY_LOGS', 'activity_logs', 'Cleared all activity logs');

        return response()->json(['message' => 'Activity logs cleared.']);
    }

    // ---------------------------------------------------------------
    // POST /api/settings/activity-logs/bulk-delete
    // ---------------------------------------------------------------
    public function bulkDeleteLogs(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (! is_array($ids) || empty($ids)) {
            return response()->json(['error' => 'No log IDs provided.'], 400);
        }

        ActivityLog::whereIn('id', $ids)->delete();

        ActivityLogger::log(
            $request,
            'BULK_DELETE_LOGS',
            'activity_logs',
            "Deleted " . count($ids) . " activity log(s)"
        );

        return response()->json(['message' => count($ids) . ' log(s) deleted.', 'count' => count($ids)]);
    }

    // ---------------------------------------------------------------
    // POST /api/settings/activity-logs/archive
    // ---------------------------------------------------------------
    public function archiveLogs(Request $request): JsonResponse
    {
        $days   = max(1, (int) $request->input('days', 90));
        $cutoff = now()->subDays($days)->toDateString();

        $count = ActivityLog::whereDate('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            return response()->json(['message' => 'No logs to archive.', 'count' => 0]);
        }

        ActivityLog::whereDate('created_at', '<', $cutoff)->delete();

        ActivityLogger::log(
            $request,
            'ARCHIVE_LOGS',
            'activity_logs',
            "Archived and removed {$count} log(s) older than {$days} days"
        );

        return response()->json(['message' => "{$count} log(s) archived and removed.", 'count' => $count]);
    }

    // ---------------------------------------------------------------
    // GET /api/settings/activity-logs/export  (JSON or CSV)
    // ---------------------------------------------------------------
    public function exportLogs(Request $request)
    {
        $format = $request->query('format', 'json');
        $from   = $request->query('from');
        $to     = $request->query('to');

        $query = ActivityLog::query()->orderByDesc('created_at');
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);

        $logs = $query->get();

        ActivityLogger::log(
            $request,
            'EXPORT_LOGS',
            'activity_logs',
            "Exported {$logs->count()} activity log(s) as " . strtoupper($format)
        );

        $timestamp = now()->format('YmdHis');

        if ($format === 'csv') {
            $csv  = "ID,User ID,Username,Action,Target,Description,Remarks,IP Address,Date & Time\n";
            foreach ($logs as $log) {
                $esc = fn ($v) => str_contains((string) $v, ',') || str_contains((string) $v, "\n")
                    ? '"' . str_replace('"', '""', $v) . '"'
                    : (string) $v;

                $csv .= implode(',', [
                    $log->id,
                    $log->user_id   ?? '',
                    $esc($log->username    ?? ''),
                    $esc($log->action      ?? ''),
                    $esc($log->target      ?? ''),
                    $esc($log->description ?? ''),
                    $esc($log->remarks     ?? ''),
                    $esc($log->ip_address  ?? ''),
                    $log->created_at,
                ]) . "\n";
            }

            return response($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=activity-logs-{$timestamp}.csv",
            ]);
        }

        // JSON (default)
        return response()->json([
            'logs'        => $logs,
            'total'       => $logs->count(),
            'exported_at' => now()->toISOString(),
        ])->withHeaders([
            'Content-Disposition' => "attachment; filename=activity-logs-{$timestamp}.json",
        ]);
    }
}
