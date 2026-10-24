<?php

/**
 * SPEC-013 NAV-NF04, NF05: the layout and the six home screens pass axe on
 * a wide screen and at 360 px, and at 360 px nothing scrolls sideways.
 *
 * Level 3 reports every impact, minor ones and best practices included:
 * stricter than the WCAG 2.1 AA the spec asks for.
 */
const AXE_LEVEL = 3;

beforeEach(function () {
    $this->institution = tenant(['name' => 'Colegio Norte', 'type' => 'privada', 'education_levels' => ['primaria']]);
});

dataset('home screens', [
    'Administrador' => ['Administrador', '/admin'],
    'Director' => ['Director', '/director'],
    'Coordinador' => ['Coordinador', '/coordinator'],
    'Docente' => ['Docente', '/teacher'],
    'Representante' => ['Representante', '/guardian'],
    'Estudiante' => ['Estudiante', '/student'],
]);

dataset('widths', ['wide' => [1280], 'phone' => [360]]);

test('each home screen is accessible', function (string $role, string $path, int $width) {
    $this->actingAs(userWithRole($this->institution, $role));

    visit($path)
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize($width, 800)
        ->assertNoAccessibilityIssues(AXE_LEVEL)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with('home screens')->with('widths');

test('a screen of the institution, with its menu, is accessible', function (int $width) {
    $this->actingAs(userWithRole($this->institution, 'Director'));

    visit('/estructura')
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize($width, 800)
        ->assertNoAccessibilityIssues(AXE_LEVEL)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with('widths');

test('the menu panel is accessible while open', function () {
    $this->actingAs(userWithRole($this->institution, 'Director'));

    visit('/estructura')
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize(360, 800)
        ->click('[aria-label="Abrir el menú"]')
        ->assertNoAccessibilityIssues(AXE_LEVEL);
});
