<?php

use AulaX\Evaluation\Application\Port\ClassFact;
use AulaX\Evaluation\Application\Sheets\SheetEntry;
use AulaX\Evaluation\Application\Sheets\SheetsQuery;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R07.3 – R07.5: «Notas» lists the sheets of the active year a
 * person may open: those it teaches, those of the grades it coordinates, or
 * every one for whoever manages students. Only Media General has sheets.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->classes = [
        new ClassFact(10, 2, '3.er año A', 'media_3', 'media_general', 4, 'Matemática'),
        new ClassFact(10, 2, '3.er año A', 'media_3', 'media_general', 5, 'Física'),
        new ClassFact(12, 2, '5.º año A', 'media_5', 'media_general', 4, 'Matemática'),
        new ClassFact(20, 2, '1.er grado A', 'primaria_1', 'primaria', 7, 'Lengua'),
    ];
});

function sheets(): array
{
    $scene = test()->scene;
    $list = (new SheetsQuery($scene->teaching, $scene->calendar, $scene->permissions))();

    return [$list->schoolYearId, $list->term, array_map(fn (SheetEntry $sheet) => "{$sheet->section} · {$sheet->subject}", $list->sheets)];
}

it('lists the subjects a teacher teaches, with the open term', function () {
    $this->scene->teaching->teaching = [[9, 10, 5], [9, 20, 7]];

    expect(sheets())->toBe([2, 1, ['3.er año A · Física']]);
});

it('adds the sheets of the grades a coordinator coordinates', function () {
    $this->scene->teaching->teaching = [[9, 10, 5]];
    $this->scene->teaching->coordinated[9] = ['media_5'];

    expect(sheets()[2])->toBe(['3.er año A · Física', '5.º año A · Matemática']);
});

it('lists every sheet of the year for whoever manages students', function () {
    $this->scene->permissions->seesAll = true;

    expect(sheets()[2])->toBe(['3.er año A · Matemática', '3.er año A · Física', '5.º año A · Matemática']);
});

it('opens on the last closed term when none is open, and is empty without an active year', function () {
    $this->scene->permissions->seesAll = true;
    $this->scene->calendar->terms[1]['status'] = 'closed';

    expect(sheets()[1])->toBe(1);

    $this->scene->calendar->terms[2]['status'] = 'closed';

    expect(sheets()[1])->toBe(2);

    $this->scene->calendar->activeYear = null;

    expect(sheets())->toBe([null, 1, []]);
});

it('needs ver-calificaciones', function () {
    $this->scene->permissions->view = false;

    expect(fn () => sheets())->toThrow(AuthorizationDenied::class);
});

it('lists the sections of Primaria and Inicial a guide guides, a coordinator coordinates, or every one for a manager', function () {
    $this->scene->teaching->guiding = [[9, 20]];
    $sections = fn () => array_map(fn ($section) => "{$section->section} · {$section->kind} · ".($section->guides ? 'guía' : '-'), (new SheetsQuery($this->scene->teaching, $this->scene->calendar, $this->scene->permissions))()->sections);

    expect($sections())->toBe(['1.er grado A · literal · guía']);

    $this->scene->teaching->coordinated[9] = ['preescolar_2'];

    expect($sections())->toBe(['1.er grado A · literal · guía', '2.º nivel A · report · -']);

    $this->scene->teaching->guiding = [];
    $this->scene->teaching->coordinated[9] = [];
    $this->scene->permissions->seesAll = true;

    expect($sections())->toHaveCount(2);
});
