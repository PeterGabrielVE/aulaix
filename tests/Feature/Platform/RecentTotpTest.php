<?php

use AulaX\Platform\Domain\OperatorRole;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-NF04: sensitive actions need a TOTP typed in the last 10
 * minutes; the code counts as used.
 */
beforeEach(function () {
    leaveTenant();
    $this->support = storedOperator('soporte@aulax.ve', OperatorRole::Support);
    $this->boss = signedInOperator();
});

test('right after login, a sensitive action goes through', function () {
    $this->put(adminUrl("/operadores/{$this->support->value}/estado"), ['active' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('after ten minutes it asks for a code, then lets the action through', function () {
    $this->travel(9)->minutes();
    $this->get(adminUrl('/'))->assertOk();
    $this->travel(2)->minutes();

    $this->from(adminUrl('/operadores'))->put(adminUrl("/operadores/{$this->support->value}/estado"), ['active' => false])
        ->assertRedirect(adminUrl('/confirmar-codigo'));

    $this->post(adminUrl('/confirmar-codigo'), ['code' => '000000'])->assertSessionHasErrors(['code' => 'El código no es correcto.']);

    $this->post(adminUrl('/confirmar-codigo'), ['code' => currentCode($this->boss)])->assertRedirect(adminUrl('/operadores'));

    $this->put(adminUrl("/operadores/{$this->support->value}/estado"), ['active' => false])->assertSessionHasNoErrors();
});

test('a code used at login can not confirm again', function () {
    $this->travel(11)->minutes();
    $this->get(adminUrl('/'));

    // Same 30-second step as the login code, if it is still current.
    $this->post(adminUrl('/confirmar-codigo'), ['code' => currentCode($this->boss)])->assertRedirect();
    $this->post(adminUrl('/confirmar-codigo'), ['code' => currentCode($this->boss)])->assertSessionHasErrors('code');
});
