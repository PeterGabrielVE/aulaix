<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application\Port;

use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\Geography\LegacyPlaceholder;

interface GeographyStore
{
    /**
     * Remove the provisional first-version states (see LegacyPlaceholder),
     * with their municipalities and parishes.
     */
    public function removeLegacyPlaceholders(): void;

    /**
     * Additive sync: insert or update by code (names and parents). Never
     * removes an entry.
     */
    public function upsert(GeographicCatalog $catalog): void;
}
