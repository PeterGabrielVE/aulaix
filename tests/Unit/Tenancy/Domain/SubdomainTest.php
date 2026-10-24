<?php

use AulaX\Tenancy\Domain\InvalidSubdomain;
use AulaX\Tenancy\Domain\Subdomain;

it('normalizes a subdomain to lower case without surrounding spaces', function () {
    expect(Subdomain::fromString('  Colegio-Norte ')->value)->toBe('colegio-norte');
});

it('accepts a valid DNS label', function (string $subdomain) {
    expect(Subdomain::fromString($subdomain)->value)->toBe($subdomain);
})->with(['colegio', 'colegio-norte', 'demo2', 'a', str_repeat('a', 63)]);

it('rejects a value that is not a single DNS label', function (string $subdomain) {
    expect(fn () => Subdomain::fromString($subdomain))->toThrow(InvalidSubdomain::class);
})->with([
    'with a dot' => 'colegio.norte',
    'with an underscore' => 'colegio_norte',
    'starting with a hyphen' => '-colegio',
    'ending with a hyphen' => 'colegio-',
    'too long' => str_repeat('a', 64),
    'empty' => '',
]);

it('rejects the subdomains reserved for the platform', function (string $subdomain) {
    expect(fn () => Subdomain::fromString($subdomain))->toThrow(InvalidSubdomain::class, 'reservado');
})->with(Subdomain::RESERVED);

it('rejects a reserved subdomain regardless of letter case', function () {
    expect(fn () => Subdomain::fromString(' WWW '))->toThrow(InvalidSubdomain::class, 'reservado');
});
