<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AttendanceController
 *
 * Manages the "live" attendance table — records that have been scanned
 * (via RFID) or manually entered today but have NOT yet been moved to
 * the permanent Time Records archive.
 *
 * Once the admin clicks "Save to Time Record" on the Dashboard, all rows
 * here are copied to the time_records table and this table is cleared.
 *
 * Routes (all protected by auth.session middleware):
 *   GET    /api/attendance           — list records (with optional search)
 *   POST   /api/attendance           — manually add a record
 *   PUT    /api/attendance/{id}      — update an existing record
 *   DELETE /api/attendance           — bulk-delete by an array of IDs
 *   DELETE /api/attendance/clear     — wipe the entire attendance table
 */
class AttendanceController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/attendance
    //
    // Returns all attendance records ordered by most-recent time_in.
    // Accepts an optional ?search= query parameter that filters across
    // id_number, last_name, first_name, and middle_initial columns.
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        // Read the optional search string from the query string
        $search = $request->query('search');

        // Start building the query — always order newest scans first
        $query = Attendance::query()->orderByDesc('time_in');

        // If a search term was provided, apply a case-insensitive LIKE
        // across all name/ID columns using an OR group
        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('id_number',        'like', $like)
                  ->orWhere('last_name',       'like', $like)
                  ->orWhere('first_name',      'like', $like)
                  ->orWhere('middle_initial',  'like', $like);
            });
        }

        // Return all matching rows wrapped in a "records" key so the
        // frontend can access data.records consistently
        return response()->json(['records' => $query->get()]);
    }

    // ---------------------------------------------------------------
    // POST /api/attendance
    //
    // Manually adds a single attendance record.  Used from the Dashboard
    // "New" button when the admin needs to enter a record by hand instead
    // of via the RFID scanner.
    //
    // Required fields: id_number, last_name, first_name
    // Optional fields: middle_initial, time_in, time_out, date, remarks
    // ---------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        // Read all expected fields from the JSON request body
        $idNumber      = $request->input('id_number');
        $lastName      = $request->input('last_name');
        $firstName     = $request->input('first_name');
        $middleInitial = $request->input('middle_initial');
        $timeIn        = $request->input('time_in');   // HH:MM:SS string from <input type="time">
        $timeOut       = $request->input('time_out');  // HH:MM:SS string, may be empty
        $date          = $request->input('date');      // YYYY-MM-DD string from <input type="date">
        $remarks       = $request->input('remarks');

        // Validate the three required fields before touching the database
        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        // Default to today's date if none was provided
        $dateStr = $date ?: now()->toDateString();

        // Combine the date and time strings into full datetime strings
        // that MySQL can store. Leave null if time wasn't provided.
        $timeInDate  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutDate = $timeOut ? "{$dateStr} {$timeOut}" : null;

        // Insert the new attendance row
        Attendance::create([
            'id_number'      => $idNumber,
            'last_name'      => $lastName,
            'first_name'     => $firstName,
            'middle_initial' => $middleInitial ?: null,
            'time_in'        => $timeInDate,
            'time_out'       => $timeOutDate,
            'date'           => $dateStr,
            'remarks'        => $remarks ?: null,
        ]);

        // Record this action in the activity log for audit purposes
        ActivityLogger::log(
            $request,
            'ADD_ATTENDANCE',
            'attendance',
            "Added attendance record for {$firstName} {$lastName} ({$idNumber}) on {$dateStr}",
            $remarks ?: null
        );

        return response()->json(['message' => 'Record added successfully.']);
    }

    // ---------------------------------------------------------------
    // PUT /api/attendance/{id}
    //
    // Updates a single attendance record by its primary key.
    // Used by the Dashboard "Edit" modal.
    //
    // Builds a human-readable diff between old and new values so the
    // activity log can show exactly what changed.
    // ---------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        // Read all fields from the request body
        $idNumber      = $request->input('id_number');
        $lastName      = $request->input('last_name');
        $firstName     = $request->input('first_name');
        $middleInitial = $request->input('middle_initial');
        $timeIn        = $request->input('time_in');
        $timeOut       = $request->input('time_out');
        $date          = $request->input('date');
        $remarks       = $request->input('remarks');

        // Required fields must still be present even on update
        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        // Find the record — return 404 if it doesn't exist
        $record = Attendance::find($id);
        if (! $record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        // Snapshot the old values before overwriting so we can diff them
        $old = $record->toArray();

        // Rebuild the datetime strings the same way as store()
        $dateStr     = $date    ?: now()->toDateString();
        $timeInDate  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutDate = $timeOut ? "{$dateStr} {$timeOut}" : null;

        // Apply the updates to the record
        $record->update([
            'id_number'      => $idNumber,
            'last_name'      => $lastName,
            'first_name'     => $firstName,
            'middle_initial' => $middleInitial ?: null,
            'time_in'        => $timeInDate,
            'time_out'       => $timeOutDate,
            'date'           => $dateStr,
            'remarks'        => $remarks ?: null,
        ]);

        // Helper closure: format a datetime string as "hh:mm AM/PM" for
        // readable log output, or "—" if the value is null/empty
        $fmt = fn ($dt) => $dt ? Carbon::parse($dt)->format('h:i A') : '—';

        // Build an array of human-readable change descriptions
        $diffs = [];
        if ((string) $old['id_number']        !== (string) $idNumber)       $diffs[] = "ID from \"{$old['id_number']}\" to \"{$idNumber}\"";
        if (($old['last_name']    ?? '') !== $lastName)                      $diffs[] = "last name from \"{$old['last_name']}\" to \"{$lastName}\"";
        if (($old['first_name']   ?? '') !== $firstName)                     $diffs[] = "first name from \"{$old['first_name']}\" to \"{$firstName}\"";
        if (($old['middle_initial'] ?? '') !== ($middleInitial ?? ''))       $diffs[] = "middle initial updated";
        if ($fmt($old['time_in'])  !== $fmt($timeInDate))                    $diffs[] = "time in from {$fmt($old['time_in'])} to {$fmt($timeInDate)}";
        if ($fmt($old['time_out']) !== $fmt($timeOutDate))                   $diffs[] = "time out from {$fmt($old['time_out'])} to {$fmt($timeOutDate)}";
        if (($old['remarks'] ?? '') !== ($remarks ?? ''))                    $diffs[] = "remarks updated";

        // Compose a single log description listing all changed fields
        $name = "{$firstName} {$lastName} ({$idNumber})";
        $desc = $diffs
            ? "Edited attendance for {$name} — " . implode('; ', $diffs)
            : "Edited attendance for {$name} (no changes detected)";

        ActivityLogger::log($request, 'EDIT_ATTENDANCE', 'attendance', $desc, $remarks ?: null);

        return response()->json(['message' => 'Record updated successfully.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/attendance  (bulk delete)
    //
    // Deletes multiple records at once.  The request body must contain
    // an "ids" array (e.g. { "ids": [1, 2, 5] }).
    // Used by the Dashboard "Delete" button after the user selects rows.
    // ---------------------------------------------------------------
    public function destroy(Request $request): JsonResponse
    {
        // Read the array of IDs to delete from the request body
        $ids = $request->input('ids', []);

        // Reject if no IDs were provided
        if (empty($ids)) {
            return response()->json(['error' => 'No IDs provided.'], 400);
        }

        // Delete all rows whose primary key is in the provided array
        Attendance::whereIn('id', $ids)->delete();

        // Log which IDs were deleted so the action can be audited
        ActivityLogger::log(
            $request,
            'DELETE_ATTENDANCE',
            'attendance',
            "Deleted " . count($ids) . " attendance record(s) (IDs: " . implode(', ', $ids) . ")"
        );

        return response()->json(['message' => 'Records deleted.']);
    }

    // ---------------------------------------------------------------
    // POST /api/attendance/manual  — PUBLIC (no login required)
    //
    // Power-outage / RFID-offline fallback.  Allows a staff member to
    // manually log a student's time-in or time-out directly from the
    // kiosk page without an admin session.
    //
    // Required fields: id_number, last_name, first_name, log_type ('time_in' | 'time_out')
    // Optional fields: middle_initial, remarks
    // The date and time are always set to NOW on the server so the
    // record cannot be backdated from the kiosk.
    // ---------------------------------------------------------------
    public function manualLog(Request $request): JsonResponse
    {
        $idNumber      = trim(strtoupper($request->input('id_number', '')));
        $lastName      = trim($request->input('last_name', ''));
        $firstName     = trim($request->input('first_name', ''));
        $middleInitial = trim($request->input('middle_initial', ''));
        $logType       = $request->input('log_type', 'time_in'); // 'time_in' or 'time_out'
        $remarks       = trim($request->input('remarks', ''));

        // Validate required fields
        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        if (! in_array($logType, ['time_in', 'time_out'])) {
            return response()->json(
                ['error' => 'log_type must be "time_in" or "time_out".'], 400
            );
        }

        $now     = now();
        $dateStr = $now->toDateString();
        $nowDt   = $now->format('Y-m-d H:i:s');

        if ($logType === 'time_in') {
            // Create a new time-in record
            Attendance::create([
                'id_number'      => $idNumber,
                'last_name'      => $lastName,
                'first_name'     => $firstName,
                'middle_initial' => $middleInitial ?: null,
                'time_in'        => $nowDt,
                'time_out'       => null,
                'date'           => $dateStr,
                'remarks'        => $remarks ?: 'Manual log (power outage)',
            ]);
        } else {
            // Find the latest open record for this student today and close it
            $open = Attendance::where('id_number', $idNumber)
                              ->whereDate('date', $dateStr)
                              ->whereNull('time_out')
                              ->orderByDesc('time_in')
                              ->first();

            if ($open) {
                $open->update(['time_out' => $nowDt]);
            } else {
                // No open record — create a complete row with both times equal
                Attendance::create([
                    'id_number'      => $idNumber,
                    'last_name'      => $lastName,
                    'first_name'     => $firstName,
                    'middle_initial' => $middleInitial ?: null,
                    'time_in'        => $nowDt,
                    'time_out'       => $nowDt,
                    'date'           => $dateStr,
                    'remarks'        => $remarks ?: 'Manual log (power outage) — no open time-in found',
                ]);
            }
        }

        ActivityLogger::log(
            $request,
            'MANUAL_LOG',
            'attendance',
            "Manual {$logType} logged for {$firstName} {$lastName} ({$idNumber}) on {$dateStr}",
            $remarks ?: null
        );

        $mi       = $middleInitial ? ' ' . $middleInitial . '.' : '';
        $fullName = "{$firstName}{$mi} {$lastName}";

        return response()->json([
            'success'   => true,
            'action'    => $logType,
            'full_name' => $fullName,
            'id_number' => $idNumber,
            'time'      => $now->format('h:i A'),
            'date'      => $dateStr,
        ]);
    }

    // ---------------------------------------------------------------
    // GET /api/attendance/dtr
    //
    // Returns combined records from BOTH the live attendance table AND
    // the permanent time_records archive for a specific person in a
    // given month/year. This ensures the DTR on the Dashboard shows the
    // full attendance history regardless of whether records have been
    // saved to Time Record or not.
    //
    // Query parameters:
    //   id_number — required
    //   month     — integer 1–12 (required)
    //   year      — 4-digit year (required)
    // ---------------------------------------------------------------
    public function dtr(Request $request): JsonResponse
    {
        $idNumber = trim($request->query('id_number', ''));
        $month    = (int) $request->query('month', 0);
        $year     = (int) $request->query('year',  0);

        if (! $idNumber || ! $month || ! $year) {
            return response()->json(['error' => 'id_number, month, and year are required.'], 400);
        }

        $cols = ['id', 'date', 'time_in', 'time_out', 'remarks',
                 'last_name', 'first_name', 'middle_initial'];

        // Live attendance records (not yet saved to Time Record)
        $liveRecords = Attendance::where('id_number', $idNumber)
            ->whereMonth('date', $month)
            ->whereYear('date',  $year)
            ->get($cols);

        // Archived time records (already saved to Time Record)
        $archivedRecords = \App\Models\TimeRecord::where('id_number', $idNumber)
            ->whereMonth('date', $month)
            ->whereYear('date',  $year)
            ->get($cols);

        // Merge both collections and sort by date ascending
        $records = $liveRecords->concat($archivedRecords)
            ->sortBy('date')
            ->values();

        // Build the person name from the first record found
        $name = null;
        if ($records->isNotEmpty()) {
            $r    = $records->first();
            $mi   = $r->middle_initial ? ' ' . $r->middle_initial . '.' : '';
            $name = "{$r->first_name}{$mi} {$r->last_name}";
        }

        return response()->json([
            'id_number' => $idNumber,
            'name'      => $name,
            'month'     => $month,
            'year'      => $year,
            'records'   => $records,
        ]);
    }

    // ---------------------------------------------------------------
    // DELETE /api/attendance/clear  (wipe entire table)
    //
    // Removes every row from the attendance table without archiving.
    // This is a destructive action used to reset the live attendance
    // list without saving to Time Records first.
    //
    // NOTE: This route must be registered BEFORE the /{id} route in
    //       api.php so Laravel doesn't treat "clear" as a numeric ID.
    // ---------------------------------------------------------------
    public function clear(Request $request): JsonResponse
    {
        // Delete every row in the attendance table
        Attendance::query()->delete();

        // Log the clear event so it's visible in the activity audit trail
        ActivityLogger::log($request, 'CLEAR_ATTENDANCE', 'attendance', 'Cleared all attendance records');

        return response()->json(['message' => 'All records cleared.']);
    }
}
