<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application\Port;

use AulaX\Catalogs\Domain\InvalidCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectCatalog;

/**
 * Where the reference subjects catalog comes from.
 */
interface SubjectSource
{
    /**
     * @throws InvalidCatalog When the reference data breaks an invariant.
     */
    public function load(): SubjectCatalog;
}
