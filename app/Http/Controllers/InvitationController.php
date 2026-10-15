<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorrectInvitationRequest;
use AulaX\IdentityAccess\Application\Members\CancelInvitationHandler;
use AulaX\IdentityAccess\Application\Members\CorrectInvitationCommand;
use AulaX\IdentityAccess\Application\Members\CorrectInvitationHandler;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\IdentityAccess\Application\Members\ResendInvitationHandler;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A manager's pending invitations: correct, resend or cancel them
 * (IDG-R04.7, IDG-R04.8, IDG-R04.10). Each change sends a new link, so the
 * previous one stops working.
 */
class InvitationController extends Controller
{
    public function edit(int $invitation, MemberDirectory $directory): Response
    {
        $view = $directory->pendingInvitation($invitation);

        abort_if($view === null, 404);

        return Inertia::render('Users/EditInvitation', [
            'invitation' => UserController::invitation($view),
        ]);
    }

    public function update(CorrectInvitationRequest $request, int $invitation, CorrectInvitationHandler $correctInvitation): RedirectResponse
    {
        $correctInvitation(new CorrectInvitationCommand(
            $invitation,
            $request->validated('name'),
            $request->validated('email'),
        ));

        return redirect()->route('users.index')
            ->with('success', "Se envió una nueva invitación a {$request->validated('email')}.");
    }

    public function resend(int $invitation, ResendInvitationHandler $resendInvitation): RedirectResponse
    {
        $resendInvitation($invitation);

        return back()->with('success', 'Se reenvió la invitación.');
    }

    public function destroy(int $invitation, CancelInvitationHandler $cancelInvitation): RedirectResponse
    {
        $cancelInvitation($invitation);

        return redirect()->route('users.index')->with('success', 'Se anuló la invitación.');
    }
}
