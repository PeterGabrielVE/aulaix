<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevisionsRequest;
use AulaX\Evaluation\Application\Revision\SaveRevisionCommand;
use AulaX\Evaluation\Application\Revision\SaveRevisionHandler;
use Illuminate\Http\RedirectResponse;

/**
 * The revisions of the failed final grades (SPEC-016): saving needs
 * gestionar-calificaciones (route middleware); that the person teaches the
 * subject in the section is checked again in the use case (RVP-NF01).
 */
class RevisionController extends Controller
{
    public function section(RevisionsRequest $request, int $year, int $section, int $subject, SaveRevisionHandler $saveRevisions): RedirectResponse
    {
        $saveRevisions(new SaveRevisionCommand($year, $section, $subject, $request->validated('revisions')));

        return back()->with('success', 'Se guardaron las revisiones.');
    }
}
