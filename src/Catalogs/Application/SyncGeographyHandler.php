<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application;

use AulaX\Catalogs\Application\Port\GeographySource;
use AulaX\Catalogs\Application\Port\GeographyStore;
use AulaX\Shared\Application\TransactionManager;

/**
 * Brings the geographic catalog in line with the reference (CAT-R01,
 * CAT-R03.1, CAT-R03.2): drops the provisional first-version catalog and
 * upserts the official one. The reference is loaded and validated before
 * anything is written, and the write is atomic.
 */
final readonly class SyncGeographyHandler
{
    public function __construct(
        private GeographySource $source,
        private GeographyStore $store,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(): void
    {
        $catalog = $this->source->load();

        $this->transactions->run(function () use ($catalog) {
            $this->store->removeLegacyPlaceholders();
            $this->store->upsert($catalog);
        });
    }
}
