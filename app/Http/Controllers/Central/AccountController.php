<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Account\CancelDeletionHandler;
use AulaX\IdentityAccess\Application\Account\ChangeNameHandler;
use AulaX\IdentityAccess\Application\Account\ChangePasswordCommand;
use AulaX\IdentityAccess\Application\Account\ChangePasswordHandler;
use AulaX\IdentityAccess\Application\Account\ConfirmEmailChangeHandler;
use AulaX\IdentityAccess\Application\Account\RequestDeletionHandler;
use AulaX\IdentityAccess\Application\Account\RequestEmailChangeHandler;
use AulaX\IdentityAccess\Application\Account\ShowAccountHandler;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's account, managed only on the central domain (IDG-R03.1,
 * IDG-R05): name, email, password, and asking to delete it.
 */
class AccountController extends Controller
{
    public function show(Request $request, ShowAccountHandler $showAccount): Response
    {
        $account = $showAccount(self::accountId($request));

        return Inertia::render('Central/Account', [
            'account' => [
                'name' => $account->name,
                'email' => $account->email,
                'pendingEmail' => $account->pendingEmail,
                'pendingDeletion' => $account->pendingDeletion,
                'deletionDueAt' => $account->deletionDueAt?->setTimezone(new DateTimeZone('America/Caracas'))->format('d/m/Y'),
            ],
            'status' => session('status'),
        ]);
    }

    public function updateName(Request $request, ChangeNameHandler $changeName): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $changeName(self::accountId($request), $request->string('name')->toString());

        return back()->with('status', 'Guardamos tu nombre.');
    }

    public function requestEmailChange(Request $request, RequestEmailChangeHandler $requestEmailChange): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $requestEmailChange(self::accountId($request), $request->string('email')->toString());

        // The same answer whether the address is free or not (IDG-R05.4).
        return back()->with('status', 'Te enviamos un enlace para confirmar el nuevo correo.');
    }

    public function confirmEmailChange(string $token, ConfirmEmailChangeHandler $confirmEmailChange): RedirectResponse
    {
        $confirmEmailChange($token);

        return redirect()->route('central.account')->with('status', 'Tu correo cambió.');
    }

    public function updatePassword(Request $request, ChangePasswordHandler $changePassword): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $changePassword(new ChangePasswordCommand(
            self::accountId($request),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        ));

        return back()->with('status', 'Tu contraseña cambió.');
    }

    /**
     * IDG-R08.1 – R08.6. The password was typed again (route middleware);
     * afterwards every session is closed, this one too.
     */
    public function requestDeletion(Request $request, RequestDeletionHandler $requestDeletion): RedirectResponse
    {
        $requestDeletion(self::accountId($request), $request->boolean('confirmation'));

        return redirect()->route('central.login')
            ->with('status', 'Tu cuenta se eliminará en 30 días. Inicia sesión antes si quieres cancelarlo.');
    }

    /**
     * IDG-R08.9.
     */
    public function cancelDeletion(Request $request, CancelDeletionHandler $cancelDeletion): RedirectResponse
    {
        $cancelDeletion(self::accountId($request));

        return redirect()->route('central.home');
    }

    private static function accountId(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }
}
