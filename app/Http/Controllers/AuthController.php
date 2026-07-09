<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * AuthController
 *
 * Handles all authentication actions for the system:
 *  - Login  : validates credentials, starts a session
 *  - Logout : destroys the session
 *  - Me     : returns the current session state (used by every page on load
 *             to decide whether to redirect to login or stay)
 *
 * All three endpoints are public (no auth.session middleware) so the
 * login page itself can call them without already being authenticated.
 *
 * POST /api/auth/login
 * POST /api/auth/logout
 * GET  /api/auth/me
 */
class AuthController extends Controller
{
    // ---------------------------------------------------------------
    // POST /api/auth/login
    //
    // Accepts a JSON body of { username, password }.
    // The "username" field accepts either the username OR email address
    // so admins can log in either way.
    //
    // On success:  starts a session, returns 200 with user info.
    // On failure:  returns 401 with an error message.
    // ---------------------------------------------------------------
    public function login(Request $request): JsonResponse
    {
        // Trim whitespace from the username to avoid invisible-character issues
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');

        // Both fields are required — reject early with a clear message
        if (! $username || ! $password) {
            return response()->json(['error' => 'Username and password are required.'], 400);
        }

        // Look up the user by username OR by email address (flexible login)
        $user = User::where('username', $username)
                    ->orWhere('email', $username)
                    ->first();

        // If the user is not found OR the password doesn't match, return 401.
        // We intentionally give the same error for both cases to avoid
        // leaking whether a username exists.
        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Invalid username or password.'], 401);
        }

        // Store user identity in the session so protected routes can read it.
        // session()->save() forces an immediate write to the session store.
        // regenerate(false) rotates the session ID (prevents session fixation)
        // but keeps the data we just wrote.
        $request->session()->put('userId',   $user->id);
        $request->session()->put('username', $user->username);
        $request->session()->put('role',     $user->role);
        $request->session()->save();
        $request->session()->regenerate(false);

        // Write an audit log entry so admins can see who logged in and when
        ActivityLogger::log($request, 'LOGIN', 'users', "User \"{$user->username}\" logged in");

        // Return minimal user info so the frontend can display the role/name
        return response()->json([
            'message' => 'Login successful.',
            'user'    => [
                'username' => $user->username,
                'role'     => $user->role,
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // POST /api/auth/logout
    //
    // Destroys the current session completely.
    // We log the event BEFORE flushing the session because the logger
    // reads the userId/username from the session — once it's gone we
    // can't identify who logged out.
    // ---------------------------------------------------------------
    public function logout(Request $request): JsonResponse
    {
        // Capture identity before we wipe the session
        $username = $request->session()->get('username', 'unknown');
        $userId   = $request->session()->get('userId');

        // Log the logout while the session is still intact
        ActivityLogger::log($request, 'LOGOUT', 'users', "User \"{$username}\" logged out");

        // flush()           — remove all session data
        // invalidate()      — delete the session file/record entirely
        // regenerateToken() — issue a new CSRF token for safety
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ---------------------------------------------------------------
    // GET /api/auth/me
    //
    // Called by every page immediately on load to check whether the
    // user is still authenticated.  Returns { loggedIn: false } with
    // a 401 if there is no session, or { loggedIn: true, username, role }
    // if a valid session exists.
    //
    // The frontend uses this response to either redirect to /login or
    // render the page normally.
    // ---------------------------------------------------------------
    public function me(Request $request): JsonResponse
    {
        // No session userId means the user is not logged in
        if (! $request->session()->has('userId')) {
            return response()->json(['loggedIn' => false], 401);
        }

        // Session is valid — return the stored identity fields
        return response()->json([
            'loggedIn' => true,
            'username' => $request->session()->get('username'),
            'role'     => $request->session()->get('role'),
        ]);
    }
}
