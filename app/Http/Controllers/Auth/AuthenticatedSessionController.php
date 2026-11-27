<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Auth\LogOutHandler;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Login and logout, from an institution's subdomain or the central domain.
 * Since SPEC-004 the login is the central one: an institution's only forwards
 * there, remembering the institution so the person comes back to it
 * (IDG-R03.2). Logging out ends the session everywhere.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(CurrentTenant $tenant): RedirectResponse
    {
        return redirect()->away(route('central.login', ['plantel' => $tenant->context()->subdomain]));
    }

    public function destroy(LogOutHandler $logOut): SymfonyResponse
    {
        $logOut();

        // From a subdomain, another domain: a full page load.
        return Inertia::location(route('central.login'));
    }
}
