<?php

namespace App\Http\Controllers;

use AulaX\AcademicStructure\Application\AssignCoordinator\AssignCoordinatorHandler;
use AulaX\AcademicStructure\Application\AssignTeacher\AssignTeacherHandler;
use AulaX\AcademicStructure\Application\RemoveCoordinator\RemoveCoordinatorHandler;
use AulaX\AcademicStructure\Application\SetGuide\SetGuideHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Who teaches, guides and coordinates in a school year (SPEC-008 EAC-R05,
 * R06). Needs gestionar-estructura (route middleware, and again in the use
 * cases); who may take each post is the use case's call.
 */
class AssignmentController extends Controller
{
    public function teacher(Request $request, int $section, int $subject, AssignTeacherHandler $assignTeacher): RedirectResponse
    {
        $assignTeacher($section, $subject, $this->account($request));

        return back()->with('success', 'Se guardó el docente de la materia.');
    }

    public function guide(Request $request, int $section, SetGuideHandler $setGuide): RedirectResponse
    {
        $setGuide($section, $this->account($request));

        return back()->with('success', 'Se guardó el docente guía.');
    }

    public function coordinator(Request $request, int $year, AssignCoordinatorHandler $assignCoordinator): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'level' => ['required', 'string', 'max:16'],
            'grades' => ['nullable', 'array', 'list', 'max:17'],
            'grades.*' => ['string', 'max:16'],
        ]);

        $assignCoordinator($year, (int) $data['account_id'], $data['level'], $data['grades'] ?? []);

        return back()->with('success', 'Se asignó el coordinador.');
    }

    public function removeCoordinator(int $year, int $account, string $level, RemoveCoordinatorHandler $removeCoordinator): RedirectResponse
    {
        $removeCoordinator($year, $account, $level);

        return back()->with('success', 'Se quitó el coordinador.');
    }

    /**
     * The person to assign, or null to leave the post empty.
     */
    private function account(Request $request): ?int
    {
        $data = $request->validate(['account_id' => ['nullable', 'integer']]);

        return isset($data['account_id']) ? (int) $data['account_id'] : null;
    }
}
