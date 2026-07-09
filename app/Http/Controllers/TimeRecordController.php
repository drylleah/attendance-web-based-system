<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TimeRecord;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * TimeRecordController
 *
 * Manages the permanent "time_records" archive table.
 * Records land here in two ways:
 *   1. The admin clicks "Save to Time Record" on the Dashboard — all
 *      rows from the live attendance table are bulk-copied here and the
 *      attendance table is then cleared.
 *   2. The admin manually adds or edits a record directly on the
 *      Time Records page.
 *
 * Routes (all protected by auth.session middleware):
 *   GET    /api/timerecord           — list with search, date range, month, pagination
 *   POST   /api/timerecord           — manually add a record
 *   POST   /api/timerecord/save      — copy all attendance rows → time_records then clear
 *   PUT    /api/timerecord/{id}      — update a single record
 *   DELETE /api/timerecord/{id}      — delete a single record
 *
 * Route order matters: /save must be registered BEFORE /{id} so that
 * Laravel does not mistake the literal string "save" for a numeric ID.
 */
class TimeRecordController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/timerecord
    //
    // Returns a paginated, filterable list of time records.
    //
    // Query parameters:
    //   search  — free-text filter across id_number and name columns
    //   from    — start date (YYYY-MM-DD) for date-range filter
    //   to      — end date  (YYYY-MM-DD) for date-range filter
    //   month   — integer 1-12 to filter by calendar month
    //   page    — page number (default 1)
    //   limit   — rows per page (default 20, max 9999)
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        // Read all supported filter/pagination parameters
        $search = $request->query('search');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $month  = $request->query('month');
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = min(9999, max(1, (int) $request->query('limit', 20)));

        $query = TimeRecord::query();

        // Free-text search across id_number and all name parts
        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('id_number',        'like', $like)
                  ->orWhere('last_name',       'like', $like)
                  ->orWhere('first_name',      'like', $like)
                  ->orWhere('middle_initial',  'like', $like);
            });
        }

        // Date-range filters — both are optional and can be combined
        if ($from)  $query->whereDate('date', '>=', $from);
        if ($to)    $query->whereDate('date', '<=', $to);

        // Month filter — e.g. ?month=3 returns only March records
        if ($month) $query->whereMonth('date', (int) $month);

        // Count total matching rows BEFORE applying the page offset
        // so the frontend can calculate total pages
        $total = $query->count();

        // Apply ordering and pagination
        $records = $query->orderByDesc('date')
                         ->orderByDesc('time_in')
                         ->offset(($page - 1) * $limit)
                         ->limit($limit)
                         ->get();

        return response()->json([
            'records' => $records,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
        ]);
    }

    // ---------------------------------------------------------------
    // POST /api/timerecord
    //
    // Manually inserts a single time record.
    // Used from the Time Records page "New Entry" modal when the admin
    // needs to add a record directly to the archive.
    //
    // Required: id_number, last_name, first_name
    // Optional: middle_initial, time_in, time_out, date, remarks
    // ---------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        // Read the expected fields from the JSON body
        $idNumber      = $request->input('id_number');
        $lastName      = $request->input('last_name');
        $firstName     = $request->input('first_name');
        $middleInitial = $request->input('middle_initial');
        $timeIn        = $request->input('time_in');   // HH:MM:SS
        $timeOut       = $request->input('time_out');  // HH:MM:SS, may be absent
        $date          = $request->input('date');      // YYYY-MM-DD
        $remarks       = $request->input('remarks');

        // Validate the three required identity fields
        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        // Default date to today if not supplied
        $dateStr    = $date    ?: now()->toDateString();

        // Combine date + time strings into full datetime values for MySQL
        $timeInStr  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutStr = $timeOut ? "{$dateStr} {$timeOut}" : null;

        // Insert the new time record and stamp saved_at with the current time
        TimeRecord::create([
            'id_number'      => $idNumber,
            'last_name'      => $lastName,
            'first_name'     => $firstName,
            'middle_initial' => $middleInitial ?: null,
            'time_in'        => $timeInStr,
            'time_out'       => $timeOutStr,
            'date'           => $dateStr,
            'remarks'        => $remarks ?: null,
            'saved_at'       => now(),
        ]);

        // Write an audit log entry for this manual addition
        ActivityLogger::log(
            $request,
            'ADD_TIME_RECORD',
            'time_records',
            "Manually added time record for {$firstName} {$lastName} ({$idNumber}) on {$dateStr}",
            $remarks ?: null
        );

        return response()->json(['message' => 'Entry added successfully.']);
    }

    // ---------------------------------------------------------------
    // PUT /api/timerecord/{id}
    //
    // Updates an existing time record by its primary key.
    // Used from the Time Records page "Edit" modal.
    //
    // Builds a human-readable diff of changes for the activity log.
    // ---------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        // Read all updatable fields from the request body
        $idNumber      = $request->input('id_number');
        $lastName      = $request->input('last_name');
        $firstName     = $request->input('first_name');
        $middleInitial = $request->input('middle_initial');
        $timeIn        = $request->input('time_in');
        $timeOut       = $request->input('time_out');
        $date          = $request->input('date');
        $remarks       = $request->input('remarks');

        // Required fields must be present even when updating
        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        // Look up the record — return 404 if it no longer exists
        $record = TimeRecord::find($id);
        if (! $record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        // Snapshot the current values before overwriting (used for diff)
        $old = $record->toArray();

        // Rebuild full datetime strings from the separate date/time inputs
        $dateStr    = $date    ?: now()->toDateString();
        $timeInStr  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutStr = $timeOut ? "{$dateStr} {$timeOut}" : null;

        // Apply the updates
        $record->update([
            'id_number'      => $idNumber,
            'last_name'      => $lastName,
            'first_name'     => $firstName,
            'middle_initial' => $middleInitial ?: null,
            'time_in'        => $timeInStr,
            'time_out'       => $timeOutStr,
            'date'           => $dateStr,
            'remarks'        => $remarks ?: null,
        ]);

        // Helper: format a datetime to "hh:mm AM/PM" for readable log output
        $fmt = fn ($dt) => $dt ? Carbon::parse($dt)->format('h:i A') : '—';

        // Build a list of which fields changed for the audit log description
        $diffs = [];
        if ((string) $old['id_number']      !== (string) $idNumber)  $diffs[] = "ID from \"{$old['id_number']}\" to \"{$idNumber}\"";
        if (($old['last_name']  ?? '') !== $lastName)                 $diffs[] = "last name updated";
        if (($old['first_name'] ?? '') !== $firstName)                $diffs[] = "first name updated";
        if ($fmt($old['time_in'])  !== $fmt($timeInStr))              $diffs[] = "time in updated";
        if ($fmt($old['time_out']) !== $fmt($timeOutStr))             $diffs[] = "time out updated";

        $name = "{$firstName} {$lastName} ({$idNumber})";
        $desc = $diffs
            ? "Edited time record for {$name} — " . implode('; ', $diffs)
            : "Edited time record for {$name} (no changes detected)";

        ActivityLogger::log($request, 'EDIT_TIME_RECORD', 'time_records', $desc, $remarks ?: null);

        return response()->json(['message' => 'Record updated successfully.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/timerecord/{id}
    //
    // Deletes a single time record by its primary key.
    // Used by the per-row delete button on the Time Records page.
    // ---------------------------------------------------------------
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Find the record or return 404
        $record = TimeRecord::find($id);
        if (! $record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        // Capture identity info for the log before deleting the row
        $name = "{$record->first_name} {$record->last_name} ({$record->id_number})";
        $date = $record->date;

        $record->delete();

        // Log the deletion with enough context to identify what was removed
        ActivityLogger::log(
            $request,
            'DELETE_TIME_RECORD',
            'time_records',
            "Deleted time record for {$name} on {$date}"
        );

        return response()->json(['message' => 'Record deleted.']);
    }

    // ---------------------------------------------------------------
    // POST /api/timerecord/save
    //
    // The core "Save to Time Record" workflow:
    //   1. Counts how many rows are in the live attendance table.
    //   2. Bulk-inserts all of them into time_records using a single
    //      INSERT … SELECT statement for efficiency.
    //   3. Deletes every row from the attendance table (clears the
    //      live scanner list so it's ready for the next session).
    //
    // This is also triggered automatically by the Dashboard when the
    // scheduled end date/time is reached in Manual mode.
    //
    // NOTE: This route is registered BEFORE /{id} in api.php to prevent
    //       Laravel from treating "save" as a numeric route parameter.
    // ---------------------------------------------------------------
    public function save(Request $request): JsonResponse
    {
        // Check that there is at least one attendance row to save
        $count = Attendance::count();

        if ($count === 0) {
            return response()->json(['error' => 'No attendance records to save.'], 400);
        }

        // Bulk INSERT using a raw SQL statement: SELECT all columns from
        // attendance and insert them directly into time_records.
        // This is a single round-trip and avoids loading all rows into PHP.
        \Illuminate\Support\Facades\DB::statement('
            INSERT INTO time_records
                (id_number, last_name, first_name, middle_initial, time_in, time_out, date, remarks, saved_at)
            SELECT
                id_number, last_name, first_name, middle_initial, time_in, time_out, date, remarks, NOW()
            FROM attendance
        ');

        // Clear the live attendance table now that all rows are safely archived
        Attendance::query()->delete();

        // Log the save event with a count so admins can verify the operation
        ActivityLogger::log(
            $request,
            'SAVE_TO_TIME_RECORDS',
            'time_records',
            "Saved {$count} attendance record(s) to Time Records and cleared the attendance table"
        );

        return response()->json([
            'message' => "{$count} record(s) saved to Time Records.",
            'count'   => $count,
        ]);
    }
}
