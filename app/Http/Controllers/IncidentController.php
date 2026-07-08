<?php

namespace App\Http\Controllers;

use App\Models\IncidentReport;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IncidentController — equivalent of src/routes/incidents.js
 *
 * GET    /api/incidents         — list with search/status/date filters + pagination
 * POST   /api/incidents         — create a new report
 * PUT    /api/incidents/{id}    — update status / remarks
 * DELETE /api/incidents/{id}    — delete a report
 * GET    /api/incidents/{id}    — get a single report
 */
class IncidentController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/incidents
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = max(1, (int) $request->query('limit', 20));

        $query = IncidentReport::query();

        if ($search) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('subject_name',  'like', $like)
                  ->orWhere('subject_id_no', 'like', $like)
                  ->orWhere('description',   'like', $like)
                  ->orWhere('reporter_name', 'like', $like);
            });
        }

        if ($status) $query->where('status', $status);
        if ($from)   $query->whereDate('created_at', '>=', $from);
        if ($to)     $query->whereDate('created_at', '<=', $to);

        $total   = $query->count();
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
    // ---------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        $subjectName  = $request->input('subject_name');
        $subjectIdNo  = $request->input('subject_id_no');
        $incidentDate = $request->input('incident_date');
        $incidentType = $request->input('incident_type', 'General');
        $description  = $request->input('description');
        $remarks      = $request->input('remarks');

        if (! $subjectName || ! $description) {
            return response()->json(
                ['error' => 'Subject name and description are required.'], 400
            );
        }

        $reporterName = $request->session()->get('username', 'unknown');
        $reportedBy   = $request->session()->get('userId');

        IncidentReport::create([
            'reported_by'   => $reportedBy,
            'reporter_name' => $reporterName,
            'subject_id_no' => $subjectIdNo  ?: null,
            'subject_name'  => $subjectName,
            'incident_date' => $incidentDate ?: null,
            'incident_type' => $incidentType ?: 'General',
            'description'   => $description,
            'remarks'       => $remarks ?: null,
            'status'        => 'open',
        ]);

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
    // ---------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        $status  = $request->input('status');
        $remarks = $request->input('remarks');

        if (! $status && $remarks === null) {
            return response()->json(['error' => 'Status or remarks must be provided.'], 400);
        }

        $report = IncidentReport::find($id);
        if (! $report) {
            return response()->json(['error' => 'Incident report not found.'], 404);
        }

        $validStatuses = ['open', 'under_review', 'resolved', 'dismissed'];
        $updates = [];

        if ($status && in_array($status, $validStatuses)) {
            $updates['status'] = $status;
        }
        if ($remarks !== null) {
            $updates['remarks'] = $remarks;
        }

        if (empty($updates)) {
            return response()->json(['error' => 'No valid fields to update.'], 400);
        }

        $report->update($updates);

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
    // ---------------------------------------------------------------
    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = IncidentReport::find($id);
        if (! $report) {
            return response()->json(['error' => 'Incident report not found.'], 404);
        }

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
    // ---------------------------------------------------------------
    public function show(int $id): JsonResponse
    {
        $report = IncidentReport::find($id);
        if (! $report) {
            return response()->json(['error' => 'Incident report not found.'], 404);
        }

        return response()->json($report);
    }
}
