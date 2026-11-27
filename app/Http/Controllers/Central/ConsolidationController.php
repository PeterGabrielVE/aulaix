<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Consolidation\CompleteConsolidationCommand;
use AulaX\IdentityAccess\Application\Consolidation\CompleteConsolidationHandler;
use AulaX\IdentityAccess\Application\Consolidation\ShowConsolidationHandler;
use AulaX\IdentityAccess\Application\Consolidation\StartConsolidationHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner of historical accounts sharing an email merges them into one
 * (IDG-R09.4), after typing their password again (IDG-NF05, route
 * middleware).
 */
class ConsolidationController extends Controller
{
    public function show(Request $request, ShowConsolidationHandler $showConsolidation): Response
    {
        return Inertia::render('Central/Consolidate', [
            'institutions' => $showConsolidation((int) $request->user()->getAuthIdentifier()),
            'status' => session('status'),
        ]);
    }

    public function sendCode(Request $request, StartConsolidationHandler $startConsolidation): RedirectResponse
    {
        $startConsolidation((int) $request->user()->getAuthIdentifier());

        return back()->with('status', 'Te enviamos un código a tu correo. Vence en 15 minutos.');
    }

    public function complete(Request $request, CompleteConsolidationHandler $completeConsolidation): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $completeConsolidation(new CompleteConsolidationCommand(
            (int) $request->user()->getAuthIdentifier(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        ));

        return redirect()->route('central.home');
    }
}
