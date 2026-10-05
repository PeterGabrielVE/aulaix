<?php

use App\Models\Institution;

test('the central domain shows the institution selector, not a tenant page', function () {
    $response = $this->get('http://'.config('app.domain').'/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('SelectInstitution'));
});

test('an active institution subdomain resolves', function () {
    $institution = tenant();

    $response = $this->get(tenantUrl($institution, '/login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Auth/Login')
        ->where('institution.subdomain', $institution->subdomain)
    );
});

test('an inactive institution subdomain is rejected', function () {
    $institution = tenant(['status' => 'inactive']);

    $response = $this->get(tenantUrl($institution, '/login'));

    $response->assertNotFound();
});

test('a subdomain with no matching institution is rejected', function () {
    $response = $this->get('http://ghost.'.config('app.domain').'/login');

    $response->assertNotFound();
});

test('the institution search endpoint only returns active institutions', function () {
    $active = Institution::factory()->create(['name' => 'Colegio Activo', 'status' => 'active']);
    Institution::factory()->create(['name' => 'Colegio Inactivo', 'status' => 'inactive']);

    $response = $this->getJson('http://'.config('app.domain').'/api/institutions/search?q=Colegio');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.subdomain', $active->subdomain);
});
