<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionRequest;
use AulaX\AcademicStructure\Application\ChangeCapacity\ChangeCapacityHandler;
use AulaX\AcademicStructure\Application\CreateSection\CreateSectionCommand;
use AulaX\AcademicStructure\Application\CreateSection\CreateSectionHandler;
use AulaX\AcademicStructure\Application\RemoveSection\RemoveSectionHandler;
use Illuminate\Http\RedirectResponse;

/**
 * The sections of a school year (SPEC-008 EAC-R02). Changing them needs
 * gestionar-estructura (route middleware, and again in the use cases).
 */
class SectionController extends Controller
{
    public function store(SectionRequest $request, int $year, CreateSectionHandler $createSection): RedirectResponse
    {
        $createSection(new CreateSectionCommand(
            $year,
            $request->validated('grade'),
            $request->validated('letter'),
            $request->validated('capacity'),
            $request->validated('capacity_reason'),
        ));

        return back()->with('success', 'Se creó la sección.');
    }

    public function capacity(SectionRequest $request, int $section, ChangeCapacityHandler $changeCapacity): RedirectResponse
    {
        $changeCapacity($section, $request->validated('capacity'), $request->validated('capacity_reason'));

        return back()->with('success', 'Se guardó el cupo.');
    }

    public function destroy(int $section, RemoveSectionHandler $removeSection): RedirectResponse
    {
        $removeSection($section);

        return back()->with('success', 'Se quitó la sección.');
    }
}
