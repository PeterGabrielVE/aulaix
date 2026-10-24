<?php

use AulaX\Shared\Domain\InstitutionId;

it('wraps a positive database id', function () {
    expect(InstitutionId::fromInt(7)->value)->toBe(7);
});

it('rejects an id that can not belong to an institution', function (int $id) {
    expect(fn () => InstitutionId::fromInt($id))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it('compares by value', function () {
    expect(InstitutionId::fromInt(7)->equals(InstitutionId::fromInt(7)))->toBeTrue()
        ->and(InstitutionId::fromInt(7)->equals(InstitutionId::fromInt(8)))->toBeFalse();
});
