<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application;

use AulaX\Catalogs\Application\Port\SubjectSource;
use AulaX\Catalogs\Application\Port\SubjectStore;
use AulaX\Shared\Application\TransactionManager;

/**
 * Brings the subjects catalog in line with the MPPE reference (CAT-R02,
 * CAT-R03.1, CAT-R03.3). The reference is loaded and validated before
 * anything is written, and the write is atomic.
 */
final readonly class SyncSubjectsHandler
{
    public function __construct(
        private SubjectSource $source,
        private SubjectStore $store,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(): void
    {
        $catalog = $this->source->load();

        $this->transactions->run(fn () => $this->store->replaceWith($catalog));
    }
}
