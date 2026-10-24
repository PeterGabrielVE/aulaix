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
        $closeTerm($year, $request->boolean('confirmation'));

        return back()->with('success', 'Se cerró el lapso.');
    }

    public function reopen(Request $request, int $year, int $term, ReopenTermHandler $reopenTerm): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $reopenTerm($year, $term, (string) $request->input('reason', ''));

        return back()->with('success', 'Se reabrió el lapso.');
    }
}
