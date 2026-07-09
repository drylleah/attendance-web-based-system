<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RfidCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RfidController
 *
 * Handles the RFID kiosk scanner and student card management.
 *
 * The scan endpoint is intentionally PUBLIC so the kiosk page at
 * /attendance can receive taps without the admin being logged in.
 * Card management (register / list / update / delete) requires an
 * active admin session.
 *
 * Routes:
 *   PUBLIC  — POST /api/rfid/scan                 — process a card tap
 *   PROTECTED (auth.session):
 *             GET    /api/rfid/cards               — list all registered students
 *             POST   /api/rfid/cards               — register a new student
 *             PUT    /api/rfid/cards/{idNumber}    — update student info / active state
 *             DELETE /api/rfid/cards/{idNumber}    — remove a student
 */
class RfidController extends Controller
{
    // ---------------------------------------------------------------
    // Private helper: normalise()
    //
    // Ensures every ID number stored or compared is in a consistent
    // format: trimmed, uppercased, and with all internal whitespace
    // collapsed. This prevents duplicate registrations caused by
    // accidental spaces or mixed casing.
    // ---------------------------------------------------------------
    private function normalise(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }

    // ---------------------------------------------------------------
    // POST /api/rfid/scan  — PUBLIC (no login required)
    //
    // Core kiosk logic — called every time a student taps their ID.
    //
    // Flow:
    //   1. Normalise and validate the incoming id_number.
    //   2. Look up the student in rfid_cards.
    //      - Not found  → 404 "not registered"
    //      - Deactivated → 403 "ID deactivated"
    //   3. Check if there is an open attendance record for today
    //      (i.e. a row with time_out = null).
    //      - Open record found  → record time_out (student leaving)
    //      - No open record     → create a new row with time_in (student arriving)
    //   4. Return the action performed plus display data for the kiosk UI.
    // ---------------------------------------------------------------
    public function scan(Request $request): JsonResponse
    {
        // Normalise the incoming ID to ensure consistent matching
        $idNumber = $this->normalise($request->input('id_number', ''));

        // id_number is mandatory — reject empty submissions immediately
        if (! $idNumber) {
            return response()->json(['error' => 'id_number is required.'], 400);
        }

        // Look up the registered student card by their normalised ID
        $student = RfidCard::where('id_number', $idNumber)->first();

        // Return 404 if the card is not in the system
        if (! $student) {
            return response()->json([
                'error'     => 'ID not registered. Please register this ID first.',
                'id_number' => $idNumber,
            ], 404);
        }

        // Return 403 if the card has been deactivated by an admin
        if (! $student->is_active) {
            return response()->json([
                'error'     => 'This ID has been deactivated.',
                'id_number' => $idNumber,
            ], 403);
        }

        // Get today's date as a plain YYYY-MM-DD string for comparisons
        $todayStr = now()->toDateString();

        // Check whether the student already tapped in today but has not yet
        // tapped out — i.e. they have an open (time_out = null) attendance row.
        // We pick the most recent one in case of data anomalies.
        $openRecord = Attendance::where('id_number', $idNumber)
                                ->whereDate('date', $todayStr)
                                ->whereNull('time_out')
                                ->orderByDesc('time_in')
                                ->first();

        if ($openRecord) {
            // ---- TIME OUT ----
            // An open record exists → the student is leaving, so we fill in time_out
            $timeOutDatetime = now()->format('Y-m-d H:i:s');
            $openRecord->update(['time_out' => $timeOutDatetime]);

            $action       = 'time_out';
            $attendanceId = $openRecord->id;
            $timeValue    = now()->format('h:i A'); // e.g. "02:35 PM" for the kiosk display
        } else {
            // ---- TIME IN ----
            // No open record → the student is arriving, so we create a new row
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

        // Build a formatted full name for the kiosk success panel
        // Middle initial gets a period appended if it exists
        $mi       = $student->middle_initial ? ' ' . $student->middle_initial . '.' : '';
        $fullName = "{$student->first_name}{$mi} {$student->last_name}";

        // Return all the data the kiosk UI needs to render the result panel
        return response()->json([
            'success'        => true,
            'action'         => $action,       // 'time_in' or 'time_out'
            'attendance_id'  => $attendanceId,
            'id_number'      => $idNumber,
            'last_name'      => $student->last_name,
            'first_name'     => $student->first_name,
            'middle_initial' => $student->middle_initial,
            'full_name'      => $fullName,
            'time'           => $timeValue,    // formatted for display e.g. "08:15 AM"
            'date'           => $todayStr,
        ]);
    }

    // ---------------------------------------------------------------
    // GET /api/rfid/cards
    //
    // Returns all registered student cards ordered by registration date
    // (newest first). Used to populate the "Registered ID" tab in the
    // Card Manager modal on the attendance kiosk page.
    // ---------------------------------------------------------------
    public function listCards(): JsonResponse
    {
        // Fetch all cards, most recently registered first
        $cards = RfidCard::orderByDesc('registered_at')->get();

        return response()->json(['cards' => $cards, 'total' => $cards->count()]);
    }

    // ---------------------------------------------------------------
    // POST /api/rfid/cards
    //
    // Registers a new student so their ID can be used at the kiosk.
    //
    // Required: id_number, last_name, first_name
    // Optional: middle_initial
    //
    // Returns 409 if the id_number is already registered (prevents
    // accidental duplicate entries).
    // ---------------------------------------------------------------
    public function registerCard(Request $request): JsonResponse
    {
        // Normalise the ID before any validation or storage
        $idNumber      = $this->normalise($request->input('id_number', ''));
        $lastName      = trim($request->input('last_name',  ''));
        $firstName     = trim($request->input('first_name', ''));
        $middleInitial = trim($request->input('middle_initial', ''));

        // All three identity fields are required
        if (! $idNumber || ! $lastName || ! $firstName) {
            return response()->json([
                'error' => 'id_number, last_name, and first_name are all required.',
            ], 400);
        }

        // Prevent registering the same ID twice
        if (RfidCard::where('id_number', $idNumber)->exists()) {
            return response()->json(
                ['error' => "ID \"{$idNumber}\" is already registered."], 409
            );
        }

        // Create the card record — new cards are active by default
        RfidCard::create([
            'id_number'      => $idNumber,
            'last_name'      => $lastName,
            'first_name'     => $firstName,
            'middle_initial' => $middleInitial ?: null, // store null if blank
            'is_active'      => 1,
        ]);

        return response()->json([
            'message'   => 'Student registered successfully.',
            'id_number' => $idNumber,
        ]);
    }

    // ---------------------------------------------------------------
    // PUT /api/rfid/cards/{idNumber}
    //
    // Updates an existing card's name fields or active/inactive status.
    // Only the fields explicitly sent in the request body are changed
    // (partial update pattern) — missing fields are left unchanged.
    //
    // Useful for correcting a name typo or deactivating a lost card.
    // ---------------------------------------------------------------
    public function updateCard(Request $request, string $idNumber): JsonResponse
    {
        // Normalise the URL segment before looking it up
        $idNumber = $this->normalise($idNumber);

        // Find the card or return 404
        $card = RfidCard::where('id_number', $idNumber)->first();
        if (! $card) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        // Build the update payload — only include fields that were sent
        $updates = [];

        if ($request->has('last_name'))      $updates['last_name']      = trim($request->input('last_name'));
        if ($request->has('first_name'))     $updates['first_name']     = trim($request->input('first_name'));
        if ($request->has('middle_initial')) $updates['middle_initial'] = trim($request->input('middle_initial')) ?: null;

        // is_active accepts a truthy/falsy value and is cast to 1 or 0
        if ($request->has('is_active'))      $updates['is_active']      = $request->input('is_active') ? 1 : 0;

        // Nothing to update — return an error rather than a no-op success
        if (empty($updates)) {
            return response()->json(['error' => 'Nothing to update.'], 400);
        }

        $card->update($updates);

        return response()->json(['message' => 'Student updated.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/rfid/cards/{idNumber}
    //
    // Permanently removes a student registration.
    // After deletion the student's ID will no longer be recognised
    // at the kiosk until they are re-registered.
    // ---------------------------------------------------------------
    public function deleteCard(string $idNumber): JsonResponse
    {
        // Normalise the URL segment before looking it up
        $idNumber = $this->normalise($idNumber);

        // Find the card or return 404
        $card = RfidCard::where('id_number', $idNumber)->first();
        if (! $card) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        $card->delete();

        return response()->json(['message' => 'Student removed.']);
    }
}
