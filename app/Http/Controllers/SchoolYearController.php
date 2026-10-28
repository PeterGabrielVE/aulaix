<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolYearRequest;
use AulaX\AcademicCalendar\Application\ActivateSchoolYear\ActivateSchoolYearHandler;
use AulaX\AcademicCalendar\Application\ChangeSchoolYearDates\ChangeSchoolYearDatesCommand;
use AulaX\AcademicCalendar\Application\ChangeSchoolYearDates\ChangeSchoolYearDatesHandler;
use AulaX\AcademicCalendar\Application\CloseSchoolYear\CloseSchoolYearHandler;
use AulaX\AcademicCalendar\Application\CreateSchoolYear\CreateSchoolYearCommand;
use AulaX\AcademicCalendar\Application\CreateSchoolYear\CreateSchoolYearHandler;
use AulaX\AcademicCalendar\Application\DeleteSchoolYear\DeleteSchoolYearHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicCalendar\Application\ListSchoolYears\TermView;
use AulaX\Enrollment\Application\Roster\PendingResultsQuery;
use AulaX\Enrollment\Application\Roster\PendingSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school calendar of the institution (SPEC-007): its years and their
 * life cycle. Access needs gestionar-calendario (route middleware); every
 * rule lives in the use cases.
 */
class SchoolYearController extends Controller
{
    public function index(ListSchoolYearsHandler $listSchoolYears, PendingResultsQuery $pendingResults): Response
    {
        $years = $listSchoolYears();
        $active = array_values(array_filter($years, fn (SchoolYearView $year) => $year->status === 'active'))[0] ?? null;

        return Inertia::render('Calendar/Index', [
            // SPEC-010 MAT-R16.1: who still lacks the result of the active year, before closing it.
            'pendingResults' => $active === null ? [] : array_map(fn (PendingSection $section) => [
                'sectionId' => $section->sectionId,
                'gradeName' => $section->gradeName,
                'letter' => $section->letter,
                'count' => $section->count,
                'students' => $section->students,
            ], $pendingResults($active->id)),
            'years' => array_map(fn (SchoolYearView $year) => [
                'id' => $year->id,
                'name' => $year->name,
                'startsOn' => $year->startsOn,
                'endsOn' => $year->endsOn,
                'status' => $year->status,
                'terms' => array_map(fn (TermView $term) => [
                    'number' => $term->number,
                    'startsOn' => $term->startsOn,
                    'endsOn' => $term->endsOn,
                    'status' => $term->status,
                ], $year->terms),
            ], $years),
        ]);
    }

    public function store(SchoolYearRequest $request, CreateSchoolYearHandler $createSchoolYear): RedirectResponse
    {
        $createSchoolYear(new CreateSchoolYearCommand(
            $request->validated('starts_on'),
            $request->validated('ends_on'),
            $request->terms(),
        ));

        return back()->with('success', 'Se creó el año escolar.');
    }

    public function update(SchoolYearRequest $request, int $year, ChangeSchoolYearDatesHandler $changeDates): RedirectResponse
    {
        $changeDates(new ChangeSchoolYearDatesCommand(
            $year,
            $request->validated('starts_on'),
            $request->validated('ends_on'),
            $request->terms(),
        ));

        return back()->with('success', 'Se guardaron las fechas.');
    }

    public function destroy(Request $request, int $year, DeleteSchoolYearHandler $deleteSchoolYear): RedirectResponse
    {
        $deleteSchoolYear($year, $request->boolean('confirmation'));

        return back()->with('success', 'Se eliminó el año escolar.');
    }

    public function activate(int $year, ActivateSchoolYearHandler $activateSchoolYear): RedirectResponse
    {
        $activateSchoolYear($year);

        return back()->with('success', 'El año escolar está activo y su primer lapso, abierto.');
    }

    public function close(Request $request, int $year, CloseSchoolYearHandler $closeSchoolYear): RedirectResponse
    {
        $closeSchoolYear($year, $request->boolean('confirmation'));

        return back()->with('success', 'Se cerró el año escolar.');
    }
}
