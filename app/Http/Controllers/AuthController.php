<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * AuthController — equivalent of src/routes/auth.js
 *
 * POST /api/auth/login   — authenticate by username OR email
 * POST /api/auth/logout  — destroy session
 * GET  /api/auth/me      — return session state (used by all pages on load)
 */
class AuthController extends Controller
{
    // ---------------------------------------------------------------
    // POST /api/auth/login
    // ---------------------------------------------------------------
    public function login(Request $request): JsonResponse
    {
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');

        if (! $username || ! $password) {
            return response()->json(['error' => 'Username and password are required.'], 400);
        }

        // Match by username OR email (same as Express version)
        $user = User::where('username', $username)
                    ->orWhere('email', $username)
                    ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Invalid username or password.'], 401);
        }

        // Persist session data — mirrors req.session.userId / username / role
        $request->session()->put('userId',   $user->id);
        $request->session()->put('username', $user->username);
        $request->session()->put('role',     $user->role);
        $request->session()->save();    // force write to disk immediately
        $request->session()->regenerate(false); // rotate ID but keep data

        ActivityLogger::log($request, 'LOGIN', 'users', "User \"{$user->username}\" logged in");

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
    // ---------------------------------------------------------------
    public function logout(Request $request): JsonResponse
    {
        $username = $request->session()->get('username', 'unknown');
        $userId   = $request->session()->get('userId');

        // Log before destroying the session (same approach as Express version)
        ActivityLogger::log($request, 'LOGOUT', 'users', "User \"{$username}\" logged out");

        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ---------------------------------------------------------------
    // GET /api/auth/me
    // ---------------------------------------------------------------
    public function me(Request $request): JsonResponse
    {
        if (! $request->session()->has('userId')) {
            return response()->json(['loggedIn' => false], 401);
        }

        return response()->json([
            'loggedIn' => true,
            'username' => $request->session()->get('username'),
            'role'     => $request->session()->get('role'),
        ]);
    }
}
