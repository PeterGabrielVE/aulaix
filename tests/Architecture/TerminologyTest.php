<?php

use Symfony\Component\Finder\Finder;

/**
 * SPEC-004 IDG-NF08: the interface says «plantel», never «institución»: in
 * the pages, the translations and the emails. Code identifiers stay in
 * English ("institution"), which this does not match.
 */
test('the interface calls them planteles', function () {
    $root = dirname(__DIR__, 2);

    $files = Finder::create()->files()
        ->in(["{$root}/resources/js", "{$root}/lang/es", "{$root}/src/IdentityAccess/Infrastructure/Notifications", "{$root}/app"])
        ->name(['*.vue', '*.js', '*.php']);

    $offending = collect(iterator_to_array($files))
        ->filter(fn ($file) => preg_match('/instituci[oó]n|instituciones/iu', $file->getContents()) === 1)
        ->map(fn ($file) => str_replace($root.'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($offending)->toBe([]);
});
