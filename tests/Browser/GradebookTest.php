<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-NF05: «Notas» and the sheet pass axe on a wide screen and at
 * 360 px, nothing but the grid scrolls sideways, and the arrows move between
 * its cells. Level 3, as the other screens (SPEC-013).
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $plan = evaluationPlanRow($this->year, $this->section, $this->math);
    evaluationActivityRow($plan, ['name' => 'Prueba', 'weight' => 6000, 'position' => 0]);
    evaluationActivityRow($plan, ['name' => 'Taller', 'date' => '2026-10-12', 'weight' => 4000, 'position' => 1]);
    $this->actingAs($this->teacher);
});

function openGrades(string $path, int $width = 1280): object
{
    $test = test();

    return visit(strtr($path, ['{year}' => $test->year, '{section}' => $test->section, '{subject}' => $test->math]))
        ->withHost("{$test->institution->subdomain}.localhost")
        ->resize($width, 800);
}

test('each screen of grades is accessible', function (string $path, int $width) {
    openGrades($path, $width)
        ->assertNoAccessibilityIssues(3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with([
    'Notas' => ['/notas'],
    'Planilla' => ['/notas/{year}/secciones/{section}/materias/{subject}/lapsos/1'],
])->with(['wide' => [1280], 'phone' => [360]]);

test('the plan form is accessible while open', function () {
    openGrades('/notas/{year}/secciones/{section}/materias/{subject}/lapsos/1', 360)
        ->click('Cambiar el plan')
        ->assertSee('Suma de pesos: 100 %')
        ->assertNoAccessibilityIssues(3);
});

test('the arrows move between the cells of the grid', function () {
    openGrades('/notas/{year}/secciones/{section}/materias/{subject}/lapsos/1')
        ->click('[data-cell="0-0"]')
        ->keys('[data-cell="0-0"]', ['ArrowDown'])
        ->assertScript('document.activeElement.dataset.cell', '1-0')
        ->keys('[data-cell="1-0"]', ['ArrowRight'])
        ->assertScript('document.activeElement.dataset.cell', '1-1')
        ->keys('[data-cell="1-1"]', ['ArrowUp'])
        ->assertScript('document.activeElement.dataset.cell', '0-1');
});

test('the sheet with the column of revisions is accessible', function (int $width) {
    DB::table('terms')->update(['status' => 'closed']);
    revisionRow($this->year, $this->section, $this->math, $this->luis);

    openGrades('/notas/{year}/secciones/{section}/materias/{subject}/lapsos/3', $width)
        ->assertSee('Revisión')
        ->assertNoAccessibilityIssues(3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with(['wide' => [1280], 'phone' => [360]]);
