<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AttendanceController — equivalent of src/routes/attendance.js
 *
 * GET    /api/attendance           — list all (with optional ?search=)
 * POST   /api/attendance           — create a new record
 * PUT    /api/attendance/{id}      — update an existing record
 * DELETE /api/attendance           — bulk-delete by IDs (body: { ids: [] })
 * DELETE /api/attendance/clear     — clear the entire table
 */
class AttendanceController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/attendance
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = Attendance::query()->orderByDesc('time_in');

        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('id_number',      'like', $like)
                  ->orWhere('last_name',    'like', $like)
                  ->orWhere('first_name',   'like', $like)
                  ->orWhere('middle_initial', 'like', $like);
            });
        }

        return response()->json(['records' => $query->get()]);
    }

    // ---------------------------------------------------------------
    // POST /api/attendance
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
        $timeInDate  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutDate = $timeOut ? "{$dateStr} {$timeOut}" : null;

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

        $record = Attendance::find($id);
        if (! $record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        $old = $record->toArray();

        $dateStr     = $date    ?: now()->toDateString();
        $timeInDate  = $timeIn  ? "{$dateStr} {$timeIn}"  : null;
        $timeOutDate = $timeOut ? "{$dateStr} {$timeOut}" : null;

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

        // Build a human-readable diff (same as Express version)
        $fmt = fn ($dt) => $dt ? Carbon::parse($dt)->format('h:i A') : '—';

        $diffs = [];
        if ((string) $old['id_number']      !== (string) $idNumber)         $diffs[] = "ID from \"{$old['id_number']}\" to \"{$idNumber}\"";
        if (($old['last_name']    ?? '') !== $lastName)                      $diffs[] = "last name from \"{$old['last_name']}\" to \"{$lastName}\"";
        if (($old['first_name']   ?? '') !== $firstName)                     $diffs[] = "first name from \"{$old['first_name']}\" to \"{$firstName}\"";
        if (($old['middle_initial'] ?? '') !== ($middleInitial ?? ''))       $diffs[] = "middle initial updated";
        if ($fmt($old['time_in'])  !== $fmt($timeInDate))                    $diffs[] = "time in from {$fmt($old['time_in'])} to {$fmt($timeInDate)}";
        if ($fmt($old['time_out']) !== $fmt($timeOutDate))                   $diffs[] = "time out from {$fmt($old['time_out'])} to {$fmt($timeOutDate)}";
        if (($old['remarks'] ?? '') !== ($remarks ?? ''))                    $diffs[] = "remarks updated";

        $name = "{$firstName} {$lastName} ({$idNumber})";
        $desc = $diffs
            ? "Edited attendance for {$name} — " . implode('; ', $diffs)
            : "Edited attendance for {$name} (no changes detected)";

        ActivityLogger::log($request, 'EDIT_ATTENDANCE', 'attendance', $desc, $remarks ?: null);

        return response()->json(['message' => 'Record updated successfully.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/attendance  (bulk by IDs array in request body)
    // ---------------------------------------------------------------
    public function destroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['error' => 'No IDs provided.'], 400);
        }

        Attendance::whereIn('id', $ids)->delete();

        ActivityLogger::log(
            $request,
            'DELETE_ATTENDANCE',
            'attendance',
            "Deleted " . count($ids) . " attendance record(s) (IDs: " . implode(', ', $ids) . ")"
        );

        return response()->json(['message' => 'Records deleted.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/attendance/clear  (wipe everything)
    // ---------------------------------------------------------------
    public function clear(Request $request): JsonResponse
    {
        Attendance::query()->delete();

        ActivityLogger::log($request, 'CLEAR_ATTENDANCE', 'attendance', 'Cleared all attendance records');

        return response()->json(['message' => 'All records cleared.']);
    }
}
