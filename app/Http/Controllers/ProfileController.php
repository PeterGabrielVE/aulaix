<?php

namespace App\Http\Controllers;

use AulaX\IdentityAccess\Application\Account\LeaveInstitutionHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * "Mi perfil" inside an institution. The account (name, email, password,
 * deletion) is managed only on the central domain (IDG-R03.1); here the
 * person can only leave this institution (IDG-R07.4).
 */
class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Profile/Edit', [
            'accountUrl' => route('central.account'),
            'status' => session('status'),
        ]);
    }

    /**
     * IDG-R07.1: then back to the central domain, where their other
     * institutions are.
     */
    public function leave(Request $request, LeaveInstitutionHandler $leaveInstitution): SymfonyResponse
    {
        $leaveInstitution((int) $request->user()->getAuthIdentifier());

        return Inertia::location(route('central.home'));
    }
}
