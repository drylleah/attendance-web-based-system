<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequireLogin Middleware
 *
 * Acts as the authentication gate for all protected API routes.
 * It is registered under the alias "auth.session" in bootstrap/app.php
 * and applied to every route group that requires an active admin session.
 *
 * Why JSON instead of a redirect?
 * All protected endpoints are called by the frontend via fetch().
 * Returning a JSON 401 lets the existing JS error handlers detect
 * "not logged in" and redirect the browser to the login page, which
 * is the same behaviour the original Express requireLogin() helper had.
 *
 * Usage in api.php:
 *   Route::middleware('auth.session')->group(function () { ... });
 */
class RequireLogin
{
    /**
     * Handle an incoming request.
     *
     * Checks whether the current session contains a "userId" key.
     * - If present  → the user is authenticated; pass the request on.
     * - If absent   → the user is not logged in; return a 401 JSON error.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next     The next middleware or route handler
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for a userId key in the session — set during login
        if (! $request->session()->has('userId')) {
            // Return 401 so the frontend fetch() handlers can detect
            // the unauthenticated state and redirect to the login page
            return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
        }

        // Session is valid — allow the request to proceed to the route handler
        return $next($request);
    }
}
