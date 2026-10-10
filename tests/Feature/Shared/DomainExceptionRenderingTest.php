<?php

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Conflict;
use AulaX\Shared\Domain\NotFound;
use Illuminate\Support\Facades\Route;

/**
 * FND-R05: a use case throws a domain exception and bootstrap/app.php turns
 * it into the matching HTTP response, so controllers never translate errors.
 */
function routeThrowing(Throwable $exception): string
{
    Route::middleware('web')->post('/_test/domain-exception', fn () => throw $exception);

    return '/_test/domain-exception';
}

it('shows a broken business rule as a validation error on its form field', function () {
    $uri = routeThrowing(BusinessRuleViolation::onField('email', 'El correo ya está en uso.'));

    $this->from('/formulario')->post($uri)
        ->assertRedirect('/formulario')
        ->assertSessionHasErrors(['email' => 'El correo ya está en uso.']);
});

it('returns 422 with the field error for a broken business rule in JSON', function () {
    $uri = routeThrowing(BusinessRuleViolation::onField('email', 'El correo ya está en uso.'));

    $this->postJson($uri)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'El correo ya está en uso.']);
});

it('files a broken business rule without a field under the general error', function () {
    $uri = routeThrowing(new BusinessRuleViolation('No se puede completar la operación.'));

    $this->postJson($uri)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['general' => 'No se puede completar la operación.']);
});

it('returns 403 when the domain denies the action', function () {
    $this->postJson(routeThrowing(new AuthorizationDenied('No puedes hacer esto.')))->assertForbidden();
});

it('returns 404 when the domain does not find the resource', function () {
    $this->postJson(routeThrowing(new NotFound('No existe.')))->assertNotFound();
});

it('returns 409 with its message when the action conflicts with the current state', function () {
    $this->postJson(routeThrowing(new Conflict('Este usuario ya activó su cuenta.')))
        ->assertConflict()
        ->assertJsonPath('message', 'Este usuario ya activó su cuenta.');
});

it('does not reveal the message of an unexpected error outside debug mode', function () {
    config(['app.debug' => false]);

    $this->post(routeThrowing(new RuntimeException('SQLSTATE detalle interno')))
        ->assertInternalServerError()
        ->assertDontSee('SQLSTATE detalle interno');
});
