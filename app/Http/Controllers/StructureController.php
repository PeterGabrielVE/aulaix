<?php

namespace App\Http\Controllers;

use AulaX\AcademicStructure\Application\CopyStructure\CopyStructureHandler;
use AulaX\AcademicStructure\Application\SetCurriculum\SetCurriculumHandler;
use AulaX\AcademicStructure\Application\ShowStructure\SectionView;
use AulaX\AcademicStructure\Application\ShowStructure\ShowStructureQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The structure of the institution's school years (SPEC-008): seeing it
 * needs ver-estructura, changing it gestionar-estructura (route middleware,
 * and again in the use cases). Every rule lives in the use cases.
 */
class StructureController extends Controller
{
    public function show(Request $request, ShowStructureQuery $showStructure): Response
    {
        $year = $request->integer('anio') ?: null;
        $structure = $showStructure($year);

        return Inertia::render('Structure/Index', [
            'structure' => [
                'schoolYearId' => $structure->schoolYearId,
                'status' => $structure->status,
                'years' => $structure->years,
                'grades' => $structure->grades,
                'sections' => array_map(fn (SectionView $section) => [
                    'id' => $section->id,
                    'grade' => $section->grade,
                    'gradeName' => $section->gradeName,
                    'letter' => $section->letter,
                    'capacity' => $section->capacity,
                    'capacityReason' => $section->capacityReason,
                ], $structure->sections),
                'curriculum' => (object) $structure->curriculum,
                'subjectOptions' => (object) $structure->subjectOptions,
            ],
            'canManage' => (bool) $request->user()?->can('gestionar-estructura'),
        ]);
    }

    public function curriculum(Request $request, int $year, string $grade, SetCurriculumHandler $setCurriculum): RedirectResponse
    {
        $data = $request->validate([
            'subjects' => ['present', 'array', 'list', 'max:40'],
            'subjects.*' => ['integer'],
        ]);

        $setCurriculum($year, $grade, $data['subjects']);

        return back()->with('success', 'Se guardó el pensum.');
    }

    public function copy(Request $request, int $year, CopyStructureHandler $copyStructure): RedirectResponse
    {
        $from = $request->integer('desde') ?: null;

        $copyStructure($year, $from);

        return back()->with('success', 'Se copió la estructura del año anterior.');
    }
}
