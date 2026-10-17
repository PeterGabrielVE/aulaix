<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Auth\ConfirmPasswordHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Typing the password again before a sensitive operation, on the central
 * domain or an institution's (IDG-NF05). The routes decide how recent the
 * confirmation must be (password.confirm middleware).
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/ConfirmPassword', [
            'action' => self::isCentral($request) ? route('central.password.confirm') : route('password.confirm'),
        ]);
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request, ConfirmPasswordHandler $confirmPassword): RedirectResponse
    {
        $confirmPassword($request->string('password')->toString());

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(self::isCentral($request) ? route('central.home') : route('dashboard'));
    }

    private static function isCentral(Request $request): bool
    {
        return $request->getHost() === config('app.domain');
    }
}
