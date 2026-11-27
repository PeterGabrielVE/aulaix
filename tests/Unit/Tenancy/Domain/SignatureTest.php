<?php

use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRole;

/**
 * SPEC-006 INS-R04.2: the signatures of the institution's authorities.
 */
it('is the Director\'s or the Secretary\'s, with who signs and their position', function () {
    $signature = Signature::of(SignatureRole::Director, '  Ana Pérez ', ' Directora ', 'institutions/1/firma-director-x.png');

    expect(array_map(fn (SignatureRole $role) => $role->value, SignatureRole::cases()))->toBe(['director', 'secretary'])
        ->and($signature->signerName)->toBe('Ana Pérez')
        ->and($signature->signerTitle)->toBe('Directora')
        ->and($signature->filePath)->toBe('institutions/1/firma-director-x.png');
});

it('needs a name and a position of 120 characters at most', function () {
    try {
        Signature::of(SignatureRole::Secretary, '', str_repeat('x', 121), 'institutions/1/f.png');
    } catch (BusinessRuleViolations $violations) {
        expect(array_keys($violations->messagesByField()))->toBe(['signer_name', 'signer_title']);

        return;
    }

    $this->fail('Expected the signature to be refused.');
});
