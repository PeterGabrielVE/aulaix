<?php

require_once dirname(__DIR__).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-NF06: the screens of enrollments pass axe on a wide screen
 * and at 360 px, and at 360 px nothing scrolls sideways. Level 3, as the
 * other screens (SPEC-013).
 */
beforeEach(function () {
    $this->institution = tenant(['name' => 'Colegio Norte', 'type' => 'privada', 'education_levels' => ['primaria']]);
    $this->year = enrollmentYear(2026);
    $this->next = enrollmentYear(2027, 'planned');
    $this->section = enrollmentSection($this->year, 'primaria_3', 'A', 30);
    enrollmentSection($this->year, 'primaria_3', 'B', 30);
    enrollmentSection($this->next, 'primaria_4', 'A', 30);
    enrollmentRow(enrollmentStudent('Pérez', 'Ana'), $this->year, $this->section, ['outcome' => 'promoted', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1]);
    enrollmentRow(enrollmentStudent('Álvarez', 'Luis'), $this->year, $this->section);
    enrollmentStudent('Mora', 'Eva');
});

dataset('enrollment screens', fn () => [
    'Inscripciones' => ['/inscripciones'],
    'Sección' => ['/inscripciones/{year}/secciones/{section}'],
    'Prosecución' => ['/prosecucion/{year}/secciones/{section}'],
    'Año siguiente' => ['/prosecucion/{year}/lote'],
]);

test('each screen of enrollments is accessible', function (string $path, int $width) {
    $this->actingAs(userWithRole($this->institution, 'Director'));

    visit(strtr($path, ['{year}' => $this->year, '{section}' => $this->section]))
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize($width, 800)
        ->assertNoAccessibilityIssues(3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with('enrollment screens')->with(['wide' => [1280], 'phone' => [360]]);

test('the enrollment form of a student is accessible while open', function () {
    $this->actingAs(userWithRole($this->institution, 'Director'));

    visit('/inscripciones')
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize(360, 800)
        ->click('Inscribir')
        ->assertNoAccessibilityIssues(3);
});
