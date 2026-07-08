<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequireLogin — drop-in equivalent of the requireLogin() helper
 * used in every protected Express route.
 *
 * Returns HTTP 401 JSON when the session has no userId, so all
 * existing frontend fetch() error handlers work unchanged.
 */
class RequireLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('userId')) {
            return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
        }

        return $next($request);
    }
}
