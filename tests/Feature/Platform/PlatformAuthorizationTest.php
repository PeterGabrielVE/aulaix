<?php

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R01.4, R04, R05: the institutions of the panel are for its
 * operators only; an institution's session, even an Administrador's, opens
 * nothing there.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->administrador = userWithRole($this->institution, 'Administrador');
    leaveTenant();
});

test('without an operator session, every institution route goes to the login', function (string $method, string $path) {
    $this->call($method, adminUrl(str_replace('{id}', (string) $this->institution->id, $path)), ['active' => false, 'reason' => 'Prueba'])
        ->assertRedirect(adminUrl('/login'));

    expect($this->institution->fresh()->status->value)->toBe('active');
})->with([
    ['GET', '/planteles'],
    ['GET', '/planteles/{id}'],
    ['PUT', '/planteles/{id}/estado'],
]);

test('a member of an institution does not reach the panel', function (string $method, string $path) {
    $this->actingAs($this->administrador)
        ->call($method, adminUrl(str_replace('{id}', (string) $this->institution->id, $path)), ['active' => false, 'reason' => 'Prueba'])
        ->assertRedirect(adminUrl('/login'));

    expect($this->institution->fresh()->status->value)->toBe('active');
})->with([
    ['GET', '/planteles'],
    ['GET', '/planteles/{id}'],
    ['PUT', '/planteles/{id}/estado'],
]);
