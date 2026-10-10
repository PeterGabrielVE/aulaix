<?php

use AulaX\Catalogs\Domain\CatalogCode;
use AulaX\Catalogs\Domain\InvalidCatalog;

it('accepts the codes the catalogs use', function (string $code) {
    expect(CatalogCode::fromString($code)->value)->toBe($code);
})->with(['VE-A', 'VE-A-01', 'VE-A-01-01', 'INI-FPSC', 'MAT']);

it('rejects an empty, spaced or overlong code', function (string $code) {
    expect(fn () => CatalogCode::fromString($code))->toThrow(InvalidCatalog::class);
})->with([
    'empty' => [''],
    'blank' => ['   '],
    'inner space' => ['VE A'],
    'longer than the column' => [str_repeat('A', 256)],
]);

it('knows whether it sits under a parent code', function () {
    $municipality = CatalogCode::fromString('VE-A-01');

    expect($municipality->isChildOf(CatalogCode::fromString('VE-A')))->toBeTrue()
        ->and($municipality->isChildOf(CatalogCode::fromString('VE-B')))->toBeFalse()
        ->and(CatalogCode::fromString('VE-AB-01')->isChildOf(CatalogCode::fromString('VE-A')))->toBeFalse();
});
