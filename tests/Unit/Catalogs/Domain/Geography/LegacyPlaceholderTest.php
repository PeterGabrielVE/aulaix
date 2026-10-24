<?php

use AulaX\Catalogs\Domain\Geography\LegacyPlaceholder;

it('recognises the state codes of the provisional first-version catalog', function (string $code) {
    expect(LegacyPlaceholder::isPlaceholderState($code))->toBeTrue();
})->with(['VE-01', 'VE-24']);

it('does not mistake an official ISO state code for a placeholder', function (string $code) {
    expect(LegacyPlaceholder::isPlaceholderState($code))->toBeFalse();
})->with(['VE-A', 'VE-W', 'VE-01-01', 'VE-1', 'XVE-01']);
