<?php

use AulaX\Audit\Application\AuditOrigin;

it('records a web change with the client IP', function () {
    $origin = AuditOrigin::web('190.202.10.5');

    expect([$origin->kind, $origin->ip, $origin->operator])->toBe(['web', '190.202.10.5', null]);
});

it('records a console change with its operator', function () {
    $origin = AuditOrigin::console('soporte.ana');

    expect([$origin->kind, $origin->ip, $origin->operator])->toBe(['console', null, 'soporte.ana']);
});

it('records a change made by the system itself', function () {
    expect(AuditOrigin::system()->kind)->toBe('system');
});

it('requires a console operator', function () {
    expect(fn () => AuditOrigin::console('  '))->toThrow(LogicException::class);
});
