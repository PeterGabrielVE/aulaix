<?php

require_once dirname(__DIR__).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-NF07: the panel's login, its list of institutions and the
 * usage of one pass axe on a wide screen and at 360 px, and at 360 px
 * nothing scrolls sideways. Same level as SPEC-013's screens.
 */
const PLATFORM_AXE_LEVEL = 3;

dataset('panel widths', ['wide' => [1280], 'phone' => [360]]);

test('the panel login is accessible', function (int $width) {
    visit('/login')
        ->withHost('admin.localhost')
        ->resize($width, 800)
        ->assertNoAccessibilityIssues(PLATFORM_AXE_LEVEL)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with('panel widths');

test('the institutions and the usage of one are accessible', function (int $width) {
    tenant(['name' => 'Colegio Norte']);
    leaveTenant();
    $operator = storedOperator();

    $page = visit('/login')
        ->withHost('admin.localhost')
        ->resize($width, 800)
        ->type('email', 'ana@aulax.ve')
        ->type('password', 'clave-de-operador')
        ->type('code', currentCode($operator))
        ->press('Entrar')
        ->assertSee('Panel de plataforma')
        ->click('Planteles')
        ->assertSee('Colegio Norte')
        // Inertia's progress bar (role="bar") lives only while a visit runs.
        ->wait(1)
        ->assertNoAccessibilityIssues(PLATFORM_AXE_LEVEL)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);

    $page->click('Colegio Norte')
        ->assertSee('Estudiantes activos')
        ->wait(1)
        ->assertNoAccessibilityIssues(PLATFORM_AXE_LEVEL)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with('panel widths');
