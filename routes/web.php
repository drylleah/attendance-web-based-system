<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| These routes serve the Blade-templated HTML pages.
|
| The frontend JavaScript continues to call /api/* endpoints exactly as
| before — no changes needed to any of the existing JS files.
|
| Pages that require authentication are wrapped in the auth.session
| middleware group. Unauthenticated visitors are not redirected here —
| instead, each page's JS calls GET /api/auth/me on load and redirects
| to "/" if the response is { loggedIn: false }.
*/

// ---- Login page (public — no session required) ----
// This is the root URL and the default redirect target after logout.
Route::get('/', function () {
    return view('login');
})->name('login');

// ---- Authenticated admin pages ----
// All three pages below check the session via auth.session middleware.
// A 401 from RequireLogin causes the JS on each page to redirect to /
Route::middleware('auth.session')->group(function () {

    // Dashboard — live attendance table, stats, and quick actions
    Route::get('/dashboard',  fn () => view('dashboard'))->name('dashboard');

    // Time Record — permanent archive of saved attendance data
    Route::get('/timerecord', fn () => view('timerecord'))->name('timerecord');

    // Settings — profile, date/time config, activity logs
    Route::get('/settings',   fn () => view('settings'))->name('settings');
});

// ---- RFID / Attendance kiosk page (public — no login required) ----
// This page is intentionally public so the kiosk screen can be left
// open without an admin being permanently logged in.
Route::get('/attendance', function () {
    return view('attendance');
})->name('attendance');
