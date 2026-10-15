<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use AulaX\IdentityAccess\Application\Auth\AcceptInvitationCommand;
use AulaX\IdentityAccess\Application\Auth\AcceptInvitationHandler;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accepting an invitation, on the central domain (SPEC-004): the account is
 * global, so it is created there and not in the institution that invited.
 */
class AcceptInvitationController extends Controller
{
    /**
     * The "choose your password" screen of an invitation link.
     */
    public function create(Request $request, string $subdomain, string $token): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'email' => (string) $request->query('email', ''),
            'token' => $token,
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * Create the account and its membership (IDG-R04.4).
     */
    public function store(AcceptInvitationRequest $request, string $subdomain, AcceptInvitationHandler $acceptInvitation): RedirectResponse
    {
        $institution = $acceptInvitation(new AcceptInvitationCommand(
            $subdomain,
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        ));

        return redirect()->route('invitations.accepted', ['subdomain' => $institution->subdomain]);
    }

    /**
     * Until the central login exists (SPEC-004 PR B), the new member signs
     * in on the institution's own subdomain.
     */
    public function accepted(string $subdomain, ResolveTenantHandler $resolveTenant): Response
    {
        $institution = $resolveTenant(new ResolveTenantQuery($subdomain));

        return Inertia::render('Auth/InvitationAccepted', [
            'institutionName' => $institution->name,
            'loginUrl' => route('login', ['tenant' => $institution->subdomain]),
        ]);
    }

    /**
     * Links sent before SPEC-004 point to the institution's subdomain; they
     * keep working until they expire (IDG-R09.6).
     */
    public function legacy(Request $request, string $token, CurrentTenant $tenant): RedirectResponse
    {
        return redirect()->route('invitations.accept', [
            'subdomain' => $tenant->context()->subdomain,
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }
}
