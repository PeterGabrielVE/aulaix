<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use AulaX\Platform\Application\Access\ConfirmTotpHandler;
use AulaX\Platform\Application\Operators\RegenerateRecoveryCodesHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A fresh TOTP before sensitive actions (SPEC-014 PLT-NF04), and new
 * recovery codes for the operator itself (PLT-R01.10).
 */
class TotpController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Platform/ConfirmTotp');
    }

    public function store(Request $request, ConfirmTotpHandler $confirm): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:6']]);

        $confirm($data['code']);

        return redirect()->intended(route('platform.home'));
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesHandler $regenerate): RedirectResponse
    {
        return redirect()->route('platform.recovery-codes')->with('platform.recovery_codes', $regenerate());
    }
}
