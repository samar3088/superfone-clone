<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds anyone still carrying an emailed temporary password on the profile
 * screen until they choose their own.
 *
 * Without this the password we mailed would stay valid indefinitely, sitting in
 * an inbox as a working credential for whoever finds it.
 */
class RequirePasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_reset_password) {
            return $next($request);
        }

        // The profile screen itself, the save, and signing out must stay open —
        // anything else would be a redirect loop or a locked-in session.
        if ($request->routeIs('profile.*', 'logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(423, 'Choose a new password before continuing.');
        }

        return redirect()
            ->route('profile.edit')
            ->with('error', 'Choose your own password before you carry on — the one we emailed you is temporary.');
    }
}
