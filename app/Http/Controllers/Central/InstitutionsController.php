<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Auth\InstitutionChoice;
use AulaX\IdentityAccess\Application\Auth\IssueHandoffHandler;
use AulaX\IdentityAccess\Application\Auth\PendingInvitationView;
use AulaX\IdentityAccess\Application\Auth\ShowCentralHomeHandler;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The central home of a logged-in person: the institutions they can open
 * (IDG-R03.5), or a welcome with their pending invitations (IDG-R03.6).
 * Opening one hands the session over to its subdomain without asking for the
 * password again (IDG-R03.7).
 */
class InstitutionsController extends Controller
{
    public function show(Request $request, ShowCentralHomeHandler $showHome): Response|RedirectResponse
    {
        $home = $showHome((int) $request->user()->getAuthIdentifier());

        // During the grace period: only the date and the way back (IDG-R08.7).
        if ($home->pendingDeletion) {
            return redirect()->route('central.account');
        }

        return Inertia::render('Central/Institutions', [
            'name' => $home->name,
            'institutions' => array_map(fn (InstitutionChoice $institution) => [
                'name' => $institution->name,
                'subdomain' => $institution->subdomain,
            ], $home->institutions),
            'invitations' => array_map(fn (PendingInvitationView $invitation) => [
                'id' => $invitation->id,
                'institution' => $invitation->institution,
                'subdomain' => $invitation->subdomain,
                'roles' => $invitation->roles,
                'expiresAt' => $invitation->expiresAt->setTimezone(new DateTimeZone('America/Caracas'))->format('d/m/Y'),
            ], $home->invitations),
            'canConsolidate' => $home->canConsolidate,
        ]);
    }

    public function open(Request $request, string $subdomain, IssueHandoffHandler $issueHandoff): SymfonyResponse
    {
        // Another domain: a full page load, also for Inertia visits.
        return Inertia::location(route('auth.handoff', [
            'tenant' => $subdomain,
            'token' => $issueHandoff((int) $request->user()->getAuthIdentifier(), $subdomain),
        ]));
    }
}
