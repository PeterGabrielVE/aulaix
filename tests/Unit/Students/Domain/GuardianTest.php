<?php

use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;

require_once dirname(__DIR__, 3).'/Fixtures/Students/student_helpers.php';

/**
 * SPEC-009 EST-R03.1, R03.2, R03.8, R03.9: a guardian of the institution.
 */
function guardianData(array $data = []): GuardianData
{
    return GuardianData::fromInput(...($data + [
        'givenNames' => 'María', 'familyNames' => 'Mora', 'documentType' => 'national_id', 'document' => 'V-15.432.100',
        'phone' => '0414-5550000', 'email' => null, 'address' => null,
    ]));
}

it('registers a guardian with its required data', function () {
    $guardian = Guardian::register(guardianData());

    expect($guardian->data()->document->value())->toBe('V15432100')
        ->and($guardian->data()->givenNames)->toBe('María')
        ->and($guardian->accountId())->toBeNull();
});

it('reports every missing required datum at once', function () {
    $errors = studentViolations(fn () => guardianData(['givenNames' => '', 'familyNames' => ' ', 'document' => '', 'phone' => '']));

    expect(array_keys($errors))->toEqualCanonicalizing(['given_names', 'family_names', 'document', 'phone']);
});

it('takes a passport for a foreign guardian without cédula', function () {
    expect(guardianData(['documentType' => 'passport', 'document' => 'PA123456'])->document->value())->toBe('PA123456');
});

it('keeps the optional email and address, or none', function () {
    $full = guardianData(['email' => ' Maria@Correo.VE ', 'address' => 'Calle 5']);

    expect($full->email)->toBe('maria@correo.ve')
        ->and($full->address)->toBe('Calle 5')
        ->and(guardianData()->email)->toBeNull()
        ->and(studentViolations(fn () => guardianData(['email' => 'no-es-correo'])))->toHaveKey('email');
});

it('is corrected, its new data replacing the old', function () {
    $guardian = Guardian::register(guardianData());

    $guardian->correct(guardianData(['phone' => '0424-1112233', 'document' => 'V15432101']));

    expect($guardian->data()->phone)->toBe('0424-1112233')
        ->and($guardian->data()->document->value())->toBe('V15432101');
});
