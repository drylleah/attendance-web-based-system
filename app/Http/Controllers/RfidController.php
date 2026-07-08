<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RfidCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RfidController — equivalent of src/routes/rfid.js
 *
 * PUBLIC (no login required):
 *   POST   /api/rfid/scan                    — process a scan (time-in / time-out toggle)
 *
 * PROTECTED (login required):
 *   GET    /api/rfid/cards                   — list all registered students
 *   POST   /api/rfid/cards                   — register a new student
 *   PUT    /api/rfid/cards/{idNumber}        — update student info / active state
 *   DELETE /api/rfid/cards/{idNumber}        — remove a student
 */
class RfidController extends Controller
{
    /**
     * Normalise an ID number: trim, uppercase, collapse internal spaces.
     * Mirrors the normalise() helper in the original rfid.js.
     */
    private function normalise(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }

    // ---------------------------------------------------------------
    // POST /api/rfid/scan  — PUBLIC endpoint
    // ---------------------------------------------------------------
    public function scan(Request $request): JsonResponse
    {
        $idNumber = $this->normalise($request->input('id_number', ''));

        if (! $idNumber) {
            return response()->json(['error' => 'id_number is required.'], 400);
        }

        // 1. Look up the registered student
        $student = RfidCard::where('id_number', $idNumber)->first();

        if (! $student) {
            return response()->json([
                'error'     => 'ID not registered. Please register this ID first.',
                'id_number' => $idNumber,
            ], 404);
        }

        if (! $student->is_active) {
            return response()->json([
                'error'     => 'This ID has been deactivated.',
                'id_number' => $idNumber,
            ], 403);
        }

        $todayStr = now()->toDateString();

        // 2. Determine time-in vs time-out
        $openRecord = Attendance::where('id_number', $idNumber)
                                ->whereDate('date', $todayStr)
                                ->whereNull('time_out')
                                ->orderByDesc('time_in')
                                ->first();

        if ($openRecord) {
            // Already timed-in today → record time_out
            $timeOutDatetime = now()->format('Y-m-d H:i:s');
            $openRecord->update(['time_out' => $timeOutDatetime]);

            $action       = 'time_out';
            $attendanceId = $openRecord->id;
            $timeValue    = now()->format('h:i A');
        } else {
            // No open record → record time_in
            $timeInDatetime = now()->format('Y-m-d H:i:s');

            $newRecord = Attendance::create([
                'id_number'      => $idNumber,
                'last_name'      => $student->last_name,
                'first_name'     => $student->first_name,
                'middle_initial' => $student->middle_initial,
                'time_in'        => $timeInDatetime,
                'date'           => $todayStr,
            ]);

            $action       = 'time_in';
            $attendanceId = $newRecord->id;
            $timeValue    = now()->format('h:i A');
        }

        $mi       = $student->middle_initial ? ' ' . $student->middle_initial . '.' : '';
        $fullName = "{$student->first_name}{$mi} {$student->last_name}";

        return response()->json([
            'success'        => true,
            'action'         => $action,
            'attendance_id'  => $attendanceId,
            'id_number'      => $idNumber,
            'last_name'      => $student->last_name,
            'first_name'     => $student->first_name,
            'middle_initial' => $student->middle_initial,
            'full_name'      => $fullName,
            'time'           => $timeValue,
            'date'           => $todayStr,
        ]);
    }

    // ---------------------------------------------------------------
    // GET /api/rfid/cards
    // ---------------------------------------------------------------
    public function listCards(): JsonResponse
    {
        $cards = RfidCard::orderByDesc('registered_at')->get();

        return response()->json(['cards' => $cards, 'total' => $cards->count()]);
    }

    // ---------------------------------------------------------------
    // POST /api/rfid/cards
    // ---------------------------------------------------------------
    public function registerCard(Request $request): JsonResponse
    {
        $idNumber      = $this->normalise($request->input('id_number', ''));
        $lastName      = trim($request->input('last_name',  ''));
        $firstName     = trim($request->input('first_name', ''));
        $middleInitial = trim($request->input('middle_initial', ''));

        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json([
                'error' => 'id_number, last_name, and first_name are all required.',
            ], 400);
        }

        // Check for duplicate
        if (RfidCard::where('id_number', $idNumber)->exists()) {
            return response()->json(
                ['error' => "ID \"{$idNumber}\" is already registered."], 409
            );
        }

        RfidCard::create([
            'id_number'      => $idNumber,
            'last_name'      => $lastName,
            'first_name'     => $firstName,
            'middle_initial' => $middleInitial ?: null,
            'is_active'      => 1,
        ]);

        return response()->json([
            'message'   => 'Student registered successfully.',
            'id_number' => $idNumber,
        ]);
    }

    // ---------------------------------------------------------------
    // PUT /api/rfid/cards/{idNumber}
    // ---------------------------------------------------------------
    public function updateCard(Request $request, string $idNumber): JsonResponse
    {
        $idNumber = $this->normalise($idNumber);

        $card = RfidCard::where('id_number', $idNumber)->first();
        if (! $card) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        $updates = [];

        if ($request->has('last_name'))      $updates['last_name']      = trim($request->input('last_name'));
        if ($request->has('first_name'))     $updates['first_name']     = trim($request->input('first_name'));
        if ($request->has('middle_initial')) $updates['middle_initial'] = trim($request->input('middle_initial')) ?: null;
        if ($request->has('is_active'))      $updates['is_active']      = $request->input('is_active') ? 1 : 0;

        if (empty($updates)) {
            return response()->json(['error' => 'Nothing to update.'], 400);
        }

        $card->update($updates);

        return response()->json(['message' => 'Student updated.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/rfid/cards/{idNumber}
    // ---------------------------------------------------------------
    public function deleteCard(string $idNumber): JsonResponse
    {
        $idNumber = $this->normalise($idNumber);

        $card = RfidCard::where('id_number', $idNumber)->first();
        if (! $card) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        $card->delete();

        return response()->json(['message' => 'Student removed.']);
    }
}
