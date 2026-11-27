<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application\Port;

use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\InvalidCatalog;

/**
 * Where the reference geographic catalog comes from.
 */
interface GeographySource
{
    /**
     * @throws InvalidCatalog When the reference data breaks an invariant.
     */
    public function load(): GeographicCatalog;
}
