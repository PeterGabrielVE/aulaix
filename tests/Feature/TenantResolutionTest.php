<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;

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

test('the institution search is rate limited per client', function () {
    $search = 'http://'.config('app.domain').'/api/institutions/search?q=Colegio';

    foreach (range(1, 30) as $attempt) {
        $this->getJson($search)->assertOk();
    }

    $this->getJson($search)->assertTooManyRequests();
});

test('the institution search treats % and _ literally', function (string $query, string $expected) {
    Institution::factory()->create(['name' => 'Colegio 100% Bilingüe']);
    Institution::factory()->create(['name' => 'Colegio_Norte']);
    Institution::factory()->create(['name' => 'Colegio Sur']);

    $response = $this->getJson('http://'.config('app.domain').'/api/institutions/search?q='.urlencode($query));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', $expected);
})->with([
    'a percent sign' => ['%', 'Colegio 100% Bilingüe'],
    'an underscore' => ['_', 'Colegio_Norte'],
]);
