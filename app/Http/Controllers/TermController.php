<?php

namespace App\Http\Controllers;

use AulaX\AcademicCalendar\Application\CloseTerm\CloseTermHandler;
use AulaX\AcademicCalendar\Application\ReopenTerm\ReopenTermHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Closing the open term and reopening the last closed one (SPEC-007
 * CAL-R04).
 */
class TermController extends Controller
{
    public function close(Request $request, int $year, CloseTermHandler $closeTerm): RedirectResponse
    {
        $request->validate(['exception_justification' => ['nullable', 'string', 'max:1000']]);

        // SPEC-011 EVA-R12.3: closing with grades pending, by exception.
        $closeTerm($year, $request->boolean('confirmation'), $request->boolean('exception') ? (string) $request->input('exception_justification', '') : null);

        return back()->with('success', 'Se cerró el lapso.');
    }

    public function reopen(Request $request, int $year, int $term, ReopenTermHandler $reopenTerm): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $reopenTerm($year, $term, (string) $request->input('reason', ''));

        return back()->with('success', 'Se reabrió el lapso.');
    }
}
