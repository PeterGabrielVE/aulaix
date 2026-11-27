<?php

/**
 * SPEC-013 NAV-R01, R02.1, R02.7 in a real browser: the side menu, the
 * panel below 1024 px, the top bar and the success messages.
 */
beforeEach(function () {
    $this->institution = tenant(['name' => 'Colegio Norte', 'type' => 'privada', 'education_levels' => ['primaria']]);
    $this->director = userWithRole($this->institution, 'Director', ['name' => 'Ana Rivas']);
    $this->actingAs($this->director);
});

function openPage(string $path, int $width = 1280): object
{
    return visit($path)
        ->withHost(test()->institution->subdomain.'.localhost')
        ->resize($width, 800);
}

test('a wide screen shows the side menu, with the current screen marked', function () {
    openPage('/estructura')
        ->assertVisible('aside [aria-label="Menú principal"]')
        ->assertScript("getComputedStyle(document.querySelector('[aria-label=\"Abrir el menú\"]')).display", 'none')
        ->assertAttribute('aside a[href$="/estructura"]', 'aria-current', 'page')
        ->assertAttributeMissing('aside a[href$="/estudiantes"]', 'aria-current')
        ->assertSee('Académico')
        ->assertNoJavaScriptErrors();
});

test('the top bar names the institution, the person and their role, with the account menu', function () {
    openPage('/estructura')
        ->assertSee('Colegio Norte')
        ->assertSee('Sin año escolar activo')
        ->assertSee('Director')
        ->click('Ana Rivas')
        ->assertSee('Mi perfil')
        ->assertSee('Cerrar sesión');
});

test('below 1024 px the menu is a panel that keeps the focus and closes with Escape', function () {
    $page = openPage('/estructura', 360)
        ->assertScript("getComputedStyle(document.querySelector('aside')).display", 'none')
        ->click('[aria-label="Abrir el menú"]')
        ->assertVisible('#mobile-menu')
        ->assertScript("document.getElementById('mobile-menu').contains(document.activeElement)", true);

    // Shift+Tab from the first item wraps to the last one, still inside.
    $page->keys('#mobile-menu', ['Shift+Tab'])
        ->assertScript("document.getElementById('mobile-menu').contains(document.activeElement)", true)
        ->keys('#mobile-menu', ['Escape'])
        ->assertMissing('#mobile-menu')
        ->assertScript("document.activeElement.getAttribute('aria-label')", 'Abrir el menú');
});

test('the panel closes with its button, and when an entry is chosen', function () {
    openPage('/estructura', 360)
        ->click('[aria-label="Abrir el menú"]')
        ->click('[aria-label="Cerrar el menú"]')
        ->assertMissing('#mobile-menu')
        ->click('[aria-label="Abrir el menú"]')
        ->click('#mobile-menu a[href$="/estudiantes"]')
        ->assertPathIs('/estudiantes')
        ->assertMissing('#mobile-menu');
});

test('the first thing to get the focus skips to the content', function () {
    openPage('/estructura')
        ->keys('#app', ['Tab'])
        ->assertScript('document.activeElement.textContent.trim()', 'Saltar al contenido');
});

test('a success message shows above the content', function () {
    $admin = userWithRole($this->institution, 'Administrador');
    $this->actingAs($admin);

    openPage('/plantel')
        ->press('Guardar datos')
        ->assertSee('Se guardaron los datos del plantel.');
});
