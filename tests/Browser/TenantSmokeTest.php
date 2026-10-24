<?php

/**
 * SPEC-013 PR D spike: the browser opens an institution at its own
 * subdomain, signed in, as every other browser test will.
 */
test('opens an institution page signed in', function () {
    $institution = tenant(['name' => 'Colegio Norte']);
    $this->actingAs(userWithRole($institution, 'Director', ['name' => 'Ana Rivas']));

    visit('/director')
        ->withHost("{$institution->subdomain}.localhost")
        ->assertSee('Colegio Norte')
        ->assertSee('Ana Rivas')
        ->assertNoJavaScriptErrors();
});
