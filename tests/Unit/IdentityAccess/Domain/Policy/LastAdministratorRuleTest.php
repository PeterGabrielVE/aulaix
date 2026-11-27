<?php

use AulaX\IdentityAccess\Domain\Policy\LastAdministratorRule;

it('refuses to leave an institution without an active administrador', function () {
    expect(LastAdministratorRule::check(activeAdministrators: 1, removesAnActiveAdministrador: true))
        ->not->toBeNull()
        ->getMessage()->toBe('El plantel debe tener al menos un Administrador activo.');
});

it('allows it while another administrador stays active', function () {
    expect(LastAdministratorRule::check(activeAdministrators: 2, removesAnActiveAdministrador: true))->toBeNull();
});

it('does not care about members who are not administrador', function () {
    expect(LastAdministratorRule::check(activeAdministrators: 1, removesAnActiveAdministrador: false))->toBeNull();
});
