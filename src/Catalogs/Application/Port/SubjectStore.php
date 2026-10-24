<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application\Port;

use AulaX\Catalogs\Domain\Subject\SubjectCatalog;

interface SubjectStore
{
    /**
     * Authoritative sync: insert or rename by code, then remove every stored
     * subject whose code the catalog no longer lists.
     */
    public function replaceWith(SubjectCatalog $catalog): void;
}
