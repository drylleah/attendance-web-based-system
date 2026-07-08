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
| All routes here are prefixed with /api automatically by bootstrap/app.php.
| This file is the direct replacement for all six Express route files.
|
| Auth is session-based. The 'auth.session' middleware alias is applied to
| all protected groups — this maps to App\Http\Middleware\RequireLogin.
|
| Route order matters: PUT /api/attendance/clear must come BEFORE
| PUT /api/attendance/{id} so Laravel does not treat "clear" as an ID.
| Same applies to /api/timerecord/save before /api/timerecord/{id}.
*/

// ==========================================================================
// AUTH  —  /api/auth/*
// ==========================================================================
Route::prefix('auth')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me',      [AuthController::class, 'me']);
});

// ==========================================================================
// ATTENDANCE  —  /api/attendance/*
// Protected by RequireLogin middleware
// ==========================================================================
Route::prefix('attendance')->middleware('auth.session')->group(function () {
    Route::get('/',       [AttendanceController::class, 'index']);
    Route::post('/',      [AttendanceController::class, 'store']);
    Route::put('/{id}',   [AttendanceController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/clear', [AttendanceController::class, 'clear']);   // must be before /{id}
    Route::delete('/',    [AttendanceController::class, 'destroy']);   // bulk delete by body IDs
});

// ==========================================================================
// TIME RECORDS  —  /api/timerecord/*
// ==========================================================================
Route::prefix('timerecord')->middleware('auth.session')->group(function () {
    Route::get('/',        [TimeRecordController::class, 'index']);
    Route::post('/',       [TimeRecordController::class, 'store']);
    Route::post('/save',   [TimeRecordController::class, 'save']);     // must be before /{id}
    Route::put('/{id}',    [TimeRecordController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [TimeRecordController::class, 'destroy'])->where('id', '[0-9]+');
});

// ==========================================================================
// SETTINGS  —  /api/settings/*
// ==========================================================================
Route::prefix('settings')->middleware('auth.session')->group(function () {

    // Profile
    Route::get('profile',  [SettingsController::class, 'getProfile']);
    Route::put('profile',  [SettingsController::class, 'updateProfile']);
    Route::put('avatar',   [SettingsController::class, 'updateAvatar']);
    Route::put('password', [SettingsController::class, 'changePassword']);

    // Date & Time config
    Route::get('datetime',             [SettingsController::class, 'getDatetime']);
    Route::put('datetime',             [SettingsController::class, 'updateDatetime']);
    Route::put('datetime/triggered',   [SettingsController::class, 'markTriggered']);

    // Activity Logs — specific sub-routes BEFORE the generic resource routes
    Route::get('activity-logs/export',      [SettingsController::class, 'exportLogs']);
    Route::post('activity-logs/bulk-delete',[SettingsController::class, 'bulkDeleteLogs']);
    Route::post('activity-logs/archive',    [SettingsController::class, 'archiveLogs']);
    Route::get('activity-logs',             [SettingsController::class, 'getActivityLogs']);
    Route::delete('activity-logs',          [SettingsController::class, 'clearActivityLogs']);
});

// ==========================================================================
// INCIDENTS  —  /api/incidents/*
// ==========================================================================
Route::prefix('incidents')->middleware('auth.session')->group(function () {
    Route::get('/',       [IncidentController::class, 'index']);
    Route::post('/',      [IncidentController::class, 'store']);
    Route::get('/{id}',   [IncidentController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}',   [IncidentController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}',[IncidentController::class, 'destroy'])->where('id', '[0-9]+');
});

// ==========================================================================
// RFID  —  /api/rfid/*
// scan is PUBLIC; card management is protected
// ==========================================================================
Route::prefix('rfid')->group(function () {
    // Public — no session required
    Route::post('scan', [RfidController::class, 'scan']);

    // Protected card management
    Route::middleware('auth.session')->group(function () {
        Route::get('cards',                  [RfidController::class, 'listCards']);
        Route::post('cards',                 [RfidController::class, 'registerCard']);
        Route::put('cards/{idNumber}',       [RfidController::class, 'updateCard']);
        Route::delete('cards/{idNumber}',    [RfidController::class, 'deleteCard']);
    });
});
