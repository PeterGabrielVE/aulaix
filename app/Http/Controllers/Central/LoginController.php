<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CentralLoginRequest;
use AulaX\IdentityAccess\Application\Auth\InstitutionChoice;
use AulaX\IdentityAccess\Application\Auth\IssueHandoffHandler;
use AulaX\IdentityAccess\Application\Auth\LogInCommand;
use AulaX\IdentityAccess\Application\Auth\LogInHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The one login of AulaX, on the central domain (IDG-R03.1). Afterwards the
 * person goes straight into their institution when there is only one, or
 * when they came from its subdomain (IDG-R03.2, R03.4); otherwise they
 * choose (IDG-R03.5).
 */
class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
            'plantel' => $request->query('plantel'),
        ]);
    }

    public function store(CentralLoginRequest $request, LogInHandler $logIn, IssueHandoffHandler $issueHandoff): RedirectResponse|SymfonyResponse
    {
        $loggedIn = $logIn(new LogInCommand(
            $request->validated('email'),
            $request->validated('password'),
            (string) $request->ip(),
        ));

        // Back to what sent them to log in (e.g. an invitation), if anything did.
        if ($request->session()->has('url.intended')) {
            return redirect()->intended(route('central.home'));
        }

        $target = self::target($loggedIn->institutions, $request->query('plantel'));

        if ($target === null) {
            return redirect()->route('central.home');
        }

        // Another domain: a full page load, also for Inertia visits.
        return Inertia::location(route('auth.handoff', [
            'tenant' => $target->subdomain,
            'token' => $issueHandoff($loggedIn->accountId, $target->subdomain),
        ]));
    }

    /**
     * The institution to open right away, if any. One the person can not
     * open is ignored, as if it did not exist (IDG-R03.9).
     *
     * @param  list<InstitutionChoice>  $institutions
     */
    private static function target(array $institutions, mixed $requested): ?InstitutionChoice
    {
        foreach ($institutions as $institution) {
            if ($institution->subdomain === $requested) {
                return $institution;
            }
        }

        return count($institutions) === 1 ? $institutions[0] : null;
    }
}
