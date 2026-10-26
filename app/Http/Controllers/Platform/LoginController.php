<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\OperatorLoginRequest;
use AulaX\Platform\Application\Access\LogInOperatorHandler;
use AulaX\Platform\Application\Access\LogOutOperatorHandler;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The panel's login at admin.APP_DOMAIN (SPEC-014 PLT-R01): email,
 * password and code in one step.
 */
class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Platform/Login');
    }

    public function store(OperatorLoginRequest $request, LogInOperatorHandler $logIn): RedirectResponse
    {
        $left = $logIn($request->validated('email'), $request->validated('password'), $request->validated('code'));

        $redirect = redirect()->intended(route('platform.home'));

        return $left === null
            ? $redirect
            : $redirect->with('success', $left === 1 ? 'Te queda 1 código de recuperación.' : "Te quedan {$left} códigos de recuperación.");
    }

    public function destroy(LogOutOperatorHandler $logOut): RedirectResponse
    {
        $logOut();

        return redirect()->route('platform.login');
    }
}
