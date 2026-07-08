<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| These routes serve the Blade-templated HTML pages that replace the
| original static .html files from the public/ folder.
|
| The frontend JavaScript still calls /api/* endpoints exactly as before —
| no changes needed to any of the existing JS files.
|
| All pages except login are protected by the auth.session middleware so
| unauthenticated users are redirected to the login page.
*/

// ---- Login page (public) ----
Route::get('/', function () {
    return view('login');
})->name('login');

// ---- Authenticated pages ----
Route::middleware('auth.session')->group(function () {
    Route::get('/dashboard',  fn () => view('dashboard'))->name('dashboard');
    Route::get('/timerecord', fn () => view('timerecord'))->name('timerecord');
    Route::get('/settings',   fn () => view('settings'))->name('settings');
});

// ---- RFID / Attendance scanner (public kiosk page — no login required) ----
Route::get('/attendance', function () {
    return view('attendance');
})->name('attendance');
