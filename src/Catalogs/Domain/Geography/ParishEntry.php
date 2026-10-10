<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain\Geography;

use AulaX\Catalogs\Domain\CatalogCode;
use AulaX\Catalogs\Domain\InvalidCatalog;

final readonly class ParishEntry
{
    private function __construct(
        public CatalogCode $code,
        public string $name,
    ) {}

    public static function of(string $code, string $name): self
    {
        if (trim($name) === '') {
            throw InvalidCatalog::missingName($code);
        }

        return new self(CatalogCode::fromString($code), $name);
    }
}
