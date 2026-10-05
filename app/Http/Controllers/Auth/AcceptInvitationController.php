<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    /**
     * Display the "choose your password" view for an invited user.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Set the invited user's password, verify their email (they just proved
     * they receive mail there) and log them in.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $acceptedUser = null;

        $status = Password::broker('invitations')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, &$acceptedUser) {
                $user->forceFill([
                    'password' => $request->password,
                    'remember_token' => Str::random(60),
                ]);

                if ($user->markEmailAsVerified()) {
                    event(new Verified($user));
                }

                event(new PasswordReset($user));

                $acceptedUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        if (! $acceptedUser->isActive()) {
            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        Auth::login($acceptedUser);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
