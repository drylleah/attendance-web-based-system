<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TimeRecord;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * TimeRecordController — equivalent of src/routes/timerecord.js
 *
 * GET    /api/timerecord           — list with search, date range, month, pagination
 * POST   /api/timerecord           — manually add a record
 * PUT    /api/timerecord/{id}      — update a record
 * DELETE /api/timerecord/{id}      — delete a single record
 * POST   /api/timerecord/save      — copy attendance → time_records then clear attendance
 */
class TimeRecordController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/timerecord
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $month  = $request->query('month');
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = min(9999, max(1, (int) $request->query('limit', 20)));

        $query = TimeRecord::query();

        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('id_number',       'like', $like)
                  ->orWhere('last_name',     'like', $like)
                  ->orWhere('first_name',    'like', $like)
                  ->orWhere('middle_initial','like', $like);
            });
        }
        if ($from)  $query->whereDate('date', '>=', $from);
        if ($to)    $query->whereDate('date', '<=', $to);
        if ($month) $query->whereMonth('date', (int) $month);

        $total   = $query->count();
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
    // ---------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        $idNumber      = $request->input('id_number');
        $lastName      = $request->input('last_name');
        $firstName     = $request->input('first_name');
        $middleInitial = $request->input('middle_initial');
        $timeIn        = $request->input('time_in');
        $timeOut       = $request->input('time_out');
        $date          = $request->input('date');
        $remarks       = $request->input('remarks');

        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        $dateStr     = $date    ?: now()->toDateString();
        $timeInStr   = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutStr  = $timeOut ? "{$dateStr} {$timeOut}" : null;

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
    // ---------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        $idNumber      = $request->input('id_number');
        $lastName      = $request->input('last_name');
        $firstName     = $request->input('first_name');
        $middleInitial = $request->input('middle_initial');
        $timeIn        = $request->input('time_in');
        $timeOut       = $request->input('time_out');
        $date          = $request->input('date');
        $remarks       = $request->input('remarks');

        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json(
                ['error' => 'ID Number, Last Name, and First Name are required.'], 400
            );
        }

        $record = TimeRecord::find($id);
        if (! $record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        $old = $record->toArray();

        $dateStr    = $date    ?: now()->toDateString();
        $timeInStr  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutStr = $timeOut ? "{$dateStr} {$timeOut}" : null;

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

        $fmt = fn ($dt) => $dt ? Carbon::parse($dt)->format('h:i A') : '—';

        $diffs = [];
        if ((string) $old['id_number']      !== (string) $idNumber)   $diffs[] = "ID from \"{$old['id_number']}\" to \"{$idNumber}\"";
        if (($old['last_name']  ?? '') !== $lastName)                  $diffs[] = "last name updated";
        if (($old['first_name'] ?? '') !== $firstName)                 $diffs[] = "first name updated";
        if ($fmt($old['time_in'])  !== $fmt($timeInStr))               $diffs[] = "time in updated";
        if ($fmt($old['time_out']) !== $fmt($timeOutStr))              $diffs[] = "time out updated";

        $name = "{$firstName} {$lastName} ({$idNumber})";
        $desc = $diffs
            ? "Edited time record for {$name} — " . implode('; ', $diffs)
            : "Edited time record for {$name} (no changes detected)";

        ActivityLogger::log($request, 'EDIT_TIME_RECORD', 'time_records', $desc, $remarks ?: null);

        return response()->json(['message' => 'Record updated successfully.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/timerecord/{id}
    // ---------------------------------------------------------------
    public function destroy(Request $request, int $id): JsonResponse
    {
        $record = TimeRecord::find($id);
        if (! $record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        $name = "{$record->first_name} {$record->last_name} ({$record->id_number})";
        $date = $record->date;
        $record->delete();

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
    // Copy all attendance rows → time_records, then clear attendance.
    // This is the core "Save to Time Record" workflow.
    // ---------------------------------------------------------------
    public function save(Request $request): JsonResponse
    {
        $count = Attendance::count();

        if ($count === 0) {
            return response()->json(['error' => 'No attendance records to save.'], 400);
        }

        // Bulk-insert: SELECT from attendance → INSERT into time_records
        // Using raw query to mirror the original single SQL statement approach.
        \Illuminate\Support\Facades\DB::statement('
            INSERT INTO time_records
                (id_number, last_name, first_name, middle_initial, time_in, time_out, date, remarks, saved_at)
            SELECT
                id_number, last_name, first_name, middle_initial, time_in, time_out, date, remarks, NOW()
            FROM attendance
        ');

        // Clear the live attendance table
        Attendance::query()->delete();

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
