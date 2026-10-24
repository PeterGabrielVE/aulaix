<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use AulaX\IdentityAccess\Application\Auth\ConsumeHandoffHandler;
use AulaX\IdentityAccess\Application\Auth\HandoffRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the central domain hands a session over to an institution's
 * subdomain (IDG-R03.7). A rejected token ends at the central login,
 * without saying why (HandoffRejected, rendered in bootstrap/app.php).
 */
class HandoffController extends Controller
{
    /**
     * @throws HandoffRejected
     */
    public function __invoke(Request $request, ConsumeHandoffHandler $consumeHandoff): RedirectResponse
    {
        $consumeHandoff((string) $request->query('token', ''));

        return redirect()->route('dashboard');
    }
}
