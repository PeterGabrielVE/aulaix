<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AcceptOperatorLinkRequest;
use AulaX\Platform\Application\Operators\AcceptInvitationHandler;
use AulaX\Platform\Application\Operators\OpenInvitationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The link an operator receives to join the panel or enrol its TOTP again
 * (SPEC-014 PLT-R01.6, R01.10, R03.1, R03.2, R03.7).
 */
class InvitationController extends Controller
{
    private const CODES = 'platform.recovery_codes';

    public function show(string $token, OpenInvitationQuery $openInvitation): Response
    {
        $enrolment = $openInvitation($token);

        return Inertia::render('Platform/Enrol', [
            'token' => $token,
            'enrolment' => [
                'name' => $enrolment->name,
                'email' => $enrolment->email,
                'needsPassword' => $enrolment->needsPassword,
                'secret' => $enrolment->secret,
                // As an image, never inserted as markup.
                'qrImage' => 'data:image/svg+xml;base64,'.base64_encode($enrolment->qrSvg),
            ],
        ]);
    }

    public function store(string $token, AcceptOperatorLinkRequest $request, AcceptInvitationHandler $accept): RedirectResponse
    {
        $codes = $accept($token, $request->validated('password'), $request->validated('code'));

        return redirect()->route('platform.recovery-codes')->with(self::CODES, $codes);
    }

    /**
     * PLT-R01.10: the recovery codes, shown once, right after they are made.
     */
    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get(self::CODES);

        return is_array($codes)
            ? Inertia::render('Platform/RecoveryCodes', ['codes' => $codes])
            : redirect()->route('platform.login');
    }
}
