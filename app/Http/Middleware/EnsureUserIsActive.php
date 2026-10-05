<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * LoginRequest already refuses inactive users; this ends the session of a
 * user who is deactivated while logged in, on their very next request.
 *
 * Attached per route group (alias "active") rather than to the global web
 * group: loading the user needs the RLS session variable, which only
 * exists once ResolveTenant has run.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        return $next($request);
    }
}
