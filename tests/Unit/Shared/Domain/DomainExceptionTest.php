<?php

use AulaX\Shared\Domain\BusinessRuleViolation;

test('a business rule violation names the form field it belongs to', function () {
    $violation = BusinessRuleViolation::onField('email', 'El correo ya está en uso.');

    expect($violation->field())->toBe('email')
        ->and($violation->getMessage())->toBe('El correo ya está en uso.');
});

test('a business rule violation without a field belongs to no form field', function () {
    expect((new BusinessRuleViolation('Regla incumplida.'))->field())->toBeNull();
});
