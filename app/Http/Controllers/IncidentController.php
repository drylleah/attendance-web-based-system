<?php

namespace App\Http\Controllers;

use App\Models\IncidentReport;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IncidentController
 *
 * Manages incident reports that admins or staff file against students.
 * Reports can be created from the Dashboard or Time Records page via the
 * "Report Incident" button on any attendance row.
 *
 * Each report tracks: who filed it, the subject student, incident type,
 * date, description, and a workflow status (open → under_review → resolved
 * or dismissed).
 *
 * Routes (all protected by auth.session middleware):
 *   GET    /api/incidents         — list with search/status/date filters + pagination
 *   POST   /api/incidents         — create a new report
 *   GET    /api/incidents/{id}    — fetch a single report by ID
 *   PUT    /api/incidents/{id}    — update status or remarks
 *   DELETE /api/incidents/{id}    — permanently delete a report
 */
class IncidentController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/incidents
    //
    // Returns a paginated list of incident reports with optional filters.
    //
    // Query parameters:
    //   search  — free-text across subject_name, subject_id_no, description, reporter_name
    //   status  — filter by workflow status: open | under_review | resolved | dismissed
    //   from    — start date (YYYY-MM-DD) based on created_at
    //   to      — end date   (YYYY-MM-DD) based on created_at
    //   page    — page number (default 1)
    //   limit   — rows per page (default 20)
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        // Read all supported filter and pagination parameters
        $search = $request->query('search');
        $status = $request->query('status');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = max(1, (int) $request->query('limit', 20));

        $query = IncidentReport::query();

        // Full-text search across the most useful identifiers/descriptions
        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('subject_name',    'like', $like)
                  ->orWhere('subject_id_no', 'like', $like)
                  ->orWhere('description',   'like', $like)
                  ->orWhere('reporter_name', 'like', $like);
            });
        }

        // Status filter — only applies when a value is provided
        if ($status) $query->where('status', $status);

        // Date-range filter based on when the report was created
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);

        // Count total before pagination for the frontend page count
        $total = $query->count();

        // Return the most recent reports first with pagination applied
        $reports = $query->orderByDesc('created_at')
                         ->offset(($page - 1) * $limit)
                         ->limit($limit)
                         ->get();

        return response()->json([
            'reports' => $reports,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
        ]);
    }

    // ---------------------------------------------------------------
    // POST /api/incidents
    //
    // Creates a new incident report.
    //
    // Required: subject_name, description
    // Optional: subject_id_no, incident_date, incident_type, remarks
    //
    // The reporter identity (reporter_name, reported_by) is pulled from
    // the current session rather than the request body so it cannot be
    // spoofed by the client.
    //
    // New reports always start with status = "open".
    // ---------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        // Read report fields from the request body
        $subjectName  = $request->input('subject_name');
        $subjectIdNo  = $request->input('subject_id_no');
        $incidentDate = $request->input('incident_date');
        $incidentType = $request->input('incident_type', 'General'); // default to General
        $description  = $request->input('description');
        $remarks      = $request->input('remarks');

        // Both subject name and description are mandatory
        if (! $subjectName || ! $description) {
            return response()->json(
                ['error' => 'Subject name and description are required.'], 400
            );
        }

        // Pull reporter identity from the active session (tamper-proof)
        $reporterName = $request->session()->get('username', 'unknown');
        $reportedBy   = $request->session()->get('userId');

        // Insert the new report — status defaults to "open"
        IncidentReport::create([
            'reported_by'   => $reportedBy,
            'reporter_name' => $reporterName,
            'subject_id_no' => $subjectIdNo  ?: null,
            'subject_name'  => $subjectName,
            'incident_date' => $incidentDate ?: null,
            'incident_type' => $incidentType ?: 'General',
            'description'   => $description,
            'remarks'       => $remarks ?: null,
            'status'        => 'open', // all new reports begin in the open state
        ]);

        // Build a short ID suffix for the log message
        $idPart = $subjectIdNo ? " ({$subjectIdNo})" : '';

        ActivityLogger::log(
            $request,
            'CREATE_INCIDENT_REPORT',
            'incident_reports',
            "Created incident report for {$subjectName}{$idPart}",
            "Type: {$incidentType}; Date: " . ($incidentDate ?: 'N/A')
        );

        return response()->json(['message' => 'Incident report submitted successfully.']);
    }

    // ---------------------------------------------------------------
    // PUT /api/incidents/{id}
    //
    // Updates the status and/or remarks of an existing report.
    // Typically used by an admin to progress a report through its
    // workflow: open → under_review → resolved (or dismissed).
    //
    // At least one of status or remarks must be provided.
    // Invalid status values are silently ignored.
    // ---------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        $status  = $request->input('status');
        $remarks = $request->input('remarks');

        // At least one field must be provided — reject empty updates
        if (! $status && $remarks === null) {
            return response()->json(['error' => 'Status or remarks must be provided.'], 400);
        }

        // Find the report or return 404
        $report = IncidentReport::find($id);
        if (! $report) {
            return response()->json(['error' => 'Incident report not found.'], 404);
        }

        // Only allow known status values — silently skip unknown ones
        $validStatuses = ['open', 'under_review', 'resolved', 'dismissed'];
        $updates = [];

        if ($status && in_array($status, $validStatuses)) {
            $updates['status'] = $status;
        }

        // Allow remarks to be set to an empty string (clearing the field)
        if ($remarks !== null) {
            $updates['remarks'] = $remarks;
        }

        // If nothing valid was provided, return an error
        if (empty($updates)) {
            return response()->json(['error' => 'No valid fields to update.'], 400);
        }

        $report->update($updates);

        // Build a descriptive log message depending on what changed
        $logRemarks = $status ? "Status changed to: {$status}" : 'Remarks updated';
        ActivityLogger::log(
            $request,
            'UPDATE_INCIDENT_REPORT',
            'incident_reports',
            "Updated incident report #{$id} for {$report->subject_name}",
            $logRemarks
        );

        return response()->json(['message' => 'Incident report updated successfully.']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/incidents/{id}
    //
    // Permanently deletes an incident report.
    // This is irreversible — the subject's name is captured for the
    // activity log before deletion.
    // ---------------------------------------------------------------
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Find the report or return 404
        $report = IncidentReport::find($id);
        if (! $report) {
            return response()->json(['error' => 'Incident report not found.'], 404);
        }

        // Save the subject name for the log before deleting the row
        $subjectName = $report->subject_name;
        $report->delete();

        ActivityLogger::log(
            $request,
            'DELETE_INCIDENT_REPORT',
            'incident_reports',
            "Deleted incident report #{$id} for {$subjectName}"
        );

        return response()->json(['message' => 'Incident report deleted.']);
    }

    // ---------------------------------------------------------------
    // GET /api/incidents/{id}
    //
    // Returns a single incident report by its primary key.
    // Used when the UI needs to display the full details of one report.
    // ---------------------------------------------------------------
    public function show(int $id): JsonResponse
    {
        // Find the report or return 404
        $report = IncidentReport::find($id);
        if (! $report) {
            return response()->json(['error' => 'Incident report not found.'], 404);
        }

        return response()->json($report);
    }
}
