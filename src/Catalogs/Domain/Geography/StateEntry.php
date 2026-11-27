<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain\Geography;

use AulaX\Catalogs\Domain\CatalogCode;
use AulaX\Catalogs\Domain\InvalidCatalog;

/**
 * A federal entity (state or Distrito Capital), coded with its ISO 3166-2:VE code.
 */
final readonly class StateEntry
{
    /**
     * @param  list<MunicipalityEntry>  $municipalities
     */
    private function __construct(
        public CatalogCode $code,
        public string $name,
        public array $municipalities,
    ) {}

    /**
     * @param  list<MunicipalityEntry>  $municipalities
     */
    public static function of(string $code, string $name, array $municipalities): self
    {
        if (trim($name) === '') {
            throw InvalidCatalog::missingName($code);
        }

        return new self(CatalogCode::fromString($code), $name, array_values($municipalities));
    }
}
