<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Auth\PasswordResetRejected;
use AulaX\IdentityAccess\Application\Auth\ResetPasswordCommand;
use AulaX\IdentityAccess\Application\Auth\ResetPasswordHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Choosing a new password from a reset link, on the central domain
 * (IDG-R06).
 */
class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * @throws PasswordResetRejected
     */
    public function store(Request $request, ResetPasswordHandler $resetPassword): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $resetPassword(new ResetPasswordCommand(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        ));

        return redirect()->route('central.login')->with('status', __('passwords.reset'));
    }

    /**
     * An institution's own link, from before SPEC-004.
     */
    public function central(Request $request): RedirectResponse
    {
        return redirect()->away(route('password.reset', [
            'token' => $request->route('token'),
            'email' => (string) $request->query('email', ''),
        ]));
    }
}
