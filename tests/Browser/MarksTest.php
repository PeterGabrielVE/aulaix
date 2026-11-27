<?php

require_once dirname(__DIR__).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-NF05: the literals of Primaria and the reports of Inicial
 * pass axe on a wide screen and at 360 px, and nothing scrolls sideways.
 * Level 3, as the other screens (SPEC-013).
 */
beforeEach(function () {
    foreach (marksSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->actingAs($this->guide);
});

test('the literals and the reports of a section are accessible', function (string $section, int $width) {
    visit("/notas/{$this->year}/secciones/{$this->{$section}}/literales")
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize($width, 800)
        ->assertNoAccessibilityIssues(3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with(['Literales' => ['primary'], 'Informes' => ['preschool']])->with(['wide' => [1280], 'phone' => [360]]);

test('the guide records a literal from the sheet', function () {
    visit("/notas/{$this->year}/secciones/{$this->primary}/literales")
        ->withHost("{$this->institution->subdomain}.localhost")
        ->select("#literal-{$this->ana}", 'B')
        ->type("#text-{$this->ana}", 'Lee con fluidez.')
        ->press('Guardar')
        ->assertSee('Se guardó el literal.')
        ->assertSee('Lapso 1: B');
});
