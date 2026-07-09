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
