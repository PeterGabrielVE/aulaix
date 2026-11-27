<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Auth\RequestPasswordResetHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot your password?", on the central domain (IDG-R06.3).
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * The answer is the same whether the email has an account, doesn't, or
     * was already sent a link in the last minute, so the form can't be used
     * to find out which addresses are registered (IAM-R05.3, R05.4).
     */
    public function store(Request $request, RequestPasswordResetHandler $requestPasswordReset): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $requestPasswordReset($request->string('email')->toString());

        return back()->with('status', __('passwords.sent'));
    }

    /**
     * An institution's own link, from before SPEC-004.
     */
    public function central(): RedirectResponse
    {
        return redirect()->away(route('password.request'));
    }
}
