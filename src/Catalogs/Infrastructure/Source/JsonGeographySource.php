<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Source;

use AulaX\Catalogs\Application\Port\GeographySource;
use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\Geography\MunicipalityEntry;
use AulaX\Catalogs\Domain\Geography\ParishEntry;
use AulaX\Catalogs\Domain\Geography\StateEntry;
use AulaX\Catalogs\Domain\InvalidCatalog;

/**
 * Reads database/seeders/data/venezuela-divisions.json: generated from the
 * public dataset github.com/zokeber/venezuela-json, with Vargas renamed La
 * Guaira (2019) and the missing parish Mariguitar (Bolívar, Sucre) added.
 *
 * States carry their ISO 3166-2:VE code; municipality and parish codes are
 * positional within the file (VE-A-01, VE-A-01-01), so the file must only
 * ever be appended to, never reordered.
 */
final readonly class JsonGeographySource implements GeographySource
{
    public function __construct(private string $path) {}

    public function load(): GeographicCatalog
    {
        $states = json_decode(file_get_contents($this->path), associative: true, flags: JSON_THROW_ON_ERROR);

        return GeographicCatalog::of(...array_map(
            fn (mixed $state) => StateEntry::of(...[...self::codeAndName($state), array_map(
                fn (mixed $municipality) => MunicipalityEntry::of(...[...self::codeAndName($municipality), array_map(
                    fn (mixed $parish) => ParishEntry::of(...self::codeAndName($parish)),
                    self::children($municipality, 'parishes'),
                )]),
                self::children($state, 'municipalities'),
            )]),
            is_array($states) ? $states : [],
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function codeAndName(mixed $entry): array
    {
        if (! is_array($entry) || ! is_string($entry['code'] ?? null) || ! is_string($entry['name'] ?? null)) {
            throw InvalidCatalog::invalidCode(json_encode($entry) ?: '?');
        }

        return [$entry['code'], $entry['name']];
    }

    /**
     * A missing list counts as empty, so the domain reports it as an entry
     * without children instead of PHP failing on an undefined index.
     *
     * @return list<mixed>
     */
    private static function children(mixed $entry, string $key): array
    {
        return is_array($entry[$key] ?? null) ? array_values($entry[$key]) : [];
    }
}
