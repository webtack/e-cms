<?php

namespace Webtack\Ecms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a deactivated user: logs out, invalidates the session and redirects to `/login`.
 *
 * Runs in the `web` group on every request, so a session opened before the deactivation stops at the
 * next request and no session store is needed. Guests pass through.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login');
        }

        return $next($request);
    }
}
