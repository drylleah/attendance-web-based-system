<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TimeRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes defined here are automatically prefixed with /api by
| bootstrap/app.php (e.g. Route::post('auth/login') → POST /api/auth/login).
|
| Authentication is session-based.  The 'auth.session' alias maps to
| App\Http\Middleware\RequireLogin, which returns a 401 JSON response
| when no valid session exists — keeping the same contract the original
| Express requireLogin() middleware had.
|
| ⚠ ROUTE ORDER MATTERS:
|   - DELETE /api/attendance/clear  must appear BEFORE  /api/attendance/{id}
|     or Laravel will interpret the string "clear" as a numeric ID and
|     route to the wrong handler.
|   - POST /api/timerecord/save must appear BEFORE /api/timerecord/{id}
|     for the same reason.
*/

// ==========================================================================
// AUTH  —  /api/auth/*
// All three endpoints are public (no auth.session) so the login page
// and the per-page session-check calls can reach them freely.
// ==========================================================================
Route::prefix('auth')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);   // authenticate and start a session
    Route::post('logout', [AuthController::class, 'logout']);  // destroy the current session
    Route::get('me',      [AuthController::class, 'me']);      // return current session state
});

// ==========================================================================
// ATTENDANCE  —  /api/attendance/*
// Manages the "live" attendance table (records not yet archived).
// The /manual endpoint is PUBLIC so the kiosk can accept manual entries
// without an admin being logged in (power-outage fallback).
// All other endpoints require an active admin session.
// ==========================================================================

// Public — kiosk manual log (no login required)
Route::post('attendance/manual', [AttendanceController::class, 'manualLog']);

Route::prefix('attendance')->middleware('auth.session')->group(function () {
    Route::get('/',         [AttendanceController::class, 'index']);   // list with optional ?search=
    Route::post('/',        [AttendanceController::class, 'store']);   // manually add a record
    Route::put('/{id}',     [AttendanceController::class, 'update'])->where('id', '[0-9]+');  // edit by ID
    Route::delete('/clear', [AttendanceController::class, 'clear']);   // ⚠ must be BEFORE /{id} — wipe all rows
    Route::delete('/',      [AttendanceController::class, 'destroy']); // bulk delete — body: { ids: [] }
});

// ==========================================================================
// TIME RECORDS  —  /api/timerecord/*
// Manages the permanent time_records archive.
// All endpoints require an active admin session.
// ==========================================================================
Route::prefix('timerecord')->middleware('auth.session')->group(function () {
    Route::get('/',        [TimeRecordController::class, 'index']);   // list with search, date range, month
    Route::post('/',       [TimeRecordController::class, 'store']);   // manually add a record
    Route::post('/save',   [TimeRecordController::class, 'save']);    // ⚠ must be BEFORE /{id} — copy attendance → time_records
    Route::put('/{id}',    [TimeRecordController::class, 'update'])->where('id', '[0-9]+');   // edit by ID
    Route::delete('/{id}', [TimeRecordController::class, 'destroy'])->where('id', '[0-9]+'); // delete by ID
});

// ==========================================================================
// SETTINGS  —  /api/settings/*
// All settings endpoints require an active admin session.
// Sub-routes with literal path segments are registered BEFORE the
// generic resource routes to avoid parameter-matching ambiguity.
// ==========================================================================
Route::prefix('settings')->middleware('auth.session')->group(function () {

    // -- Profile --
    Route::get('profile',  [SettingsController::class, 'getProfile']);     // fetch current user profile
    Route::put('profile',  [SettingsController::class, 'updateProfile']);  // update name and email
    Route::put('avatar',   [SettingsController::class, 'updateAvatar']);   // update profile picture (base64)
    Route::put('password', [SettingsController::class, 'changePassword']); // change login password

    // -- Date & Time configuration --
    Route::get('datetime',           [SettingsController::class, 'getDatetime']);     // get current mode + schedule
    Route::put('datetime',           [SettingsController::class, 'updateDatetime']);  // set mode / schedule
    Route::put('datetime/triggered', [SettingsController::class, 'markTriggered']);   // stamp last_triggered_at after auto-save

    // -- Activity Logs --
    // Specific sub-routes MUST be registered before the generic ones to
    // prevent "export" or "bulk-delete" from being matched as a log ID.
    Route::get('activity-logs/export',       [SettingsController::class, 'exportLogs']);       // download as JSON or CSV
    Route::post('activity-logs/bulk-delete', [SettingsController::class, 'bulkDeleteLogs']);   // delete selected log IDs
    Route::post('activity-logs/archive',     [SettingsController::class, 'archiveLogs']);      // remove logs older than N days
    Route::get('activity-logs',              [SettingsController::class, 'getActivityLogs']);  // list with filters + pagination
    Route::delete('activity-logs',           [SettingsController::class, 'clearActivityLogs']); // wipe all logs
});

// ==========================================================================
// INCIDENTS  —  /api/incidents/*
// Manages incident reports filed against students.
// All endpoints require an active admin session.
// ==========================================================================
Route::prefix('incidents')->middleware('auth.session')->group(function () {
    Route::get('/',        [IncidentController::class, 'index']);                           // list with filters + pagination
    Route::post('/',       [IncidentController::class, 'store']);                           // create a new report
    Route::get('/{id}',    [IncidentController::class, 'show'])->where('id', '[0-9]+');    // fetch one report
    Route::put('/{id}',    [IncidentController::class, 'update'])->where('id', '[0-9]+'); // update status / remarks
    Route::delete('/{id}', [IncidentController::class, 'destroy'])->where('id', '[0-9]+'); // delete permanently
});

// ==========================================================================
// RFID  —  /api/rfid/*
// The scan endpoint is PUBLIC so the kiosk can receive taps without a
// logged-in admin.  Card management is protected.
// ==========================================================================
Route::prefix('rfid')->group(function () {

    // Public — the kiosk page calls this on every card tap
    Route::post('scan', [RfidController::class, 'scan']);

    // Protected — only logged-in admins can manage registered cards
    Route::middleware('auth.session')->group(function () {
        Route::get('cards',               [RfidController::class, 'listCards']);                        // list all registered students
        Route::post('cards',              [RfidController::class, 'registerCard']);                     // register a new student
        Route::put('cards/{idNumber}',    [RfidController::class, 'updateCard']);                       // update name / active state
        Route::delete('cards/{idNumber}', [RfidController::class, 'deleteCard']);                       // remove a registration
    });
});
