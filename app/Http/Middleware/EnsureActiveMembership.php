<?php

namespace App\Http\Middleware;

use AulaX\IdentityAccess\Application\Auth\HasActiveMembershipHandler;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks on every request that the logged-in person still has an active
 * membership in this institution, and an active account (IDG-R03.8). If not,
 * ends their session here only: sessions are per subdomain, so their other
 * institutions are untouched.
 *
 * Someone with no membership at all here never gets this far: Row Level
 * Security hides their account, so the guard treats them as a guest
 * (IDG-R03.9).
 *
 * Attached per route group (alias "active"), after ResolveTenant has
 * activated the institution.
 */
class EnsureActiveMembership
{
    public function __construct(private readonly HasActiveMembershipHandler $hasActiveMembership) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! ($this->hasActiveMembership)((int) $user->getAuthIdentifier())) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        return $next($request);
    }
}
