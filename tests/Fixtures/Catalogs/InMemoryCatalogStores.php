<?php

namespace Tests\Fixtures\Catalogs;

use AulaX\Catalogs\Application\Port\GeographyStore;
use AulaX\Catalogs\Application\Port\SubjectStore;
use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectCatalog;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * Records every write a sync handler asks for, and whether the transaction
 * was open at the time.
 */
final class InMemoryCatalogStores implements GeographyStore, SubjectStore
{
    /**
     * @var list<array{0: string, 1: bool}> Operation name and whether it ran inside the transaction.
     */
    public array $writes = [];

    public ?GeographicCatalog $geography = null;

    public ?SubjectCatalog $subjects = null;

    public function __construct(private readonly InlineTransactionManager $transactions) {}

    public function removeLegacyPlaceholders(): void
    {
        $this->writes[] = ['removeLegacyPlaceholders', $this->transactions->inTransaction];
    }

    public function upsert(GeographicCatalog $catalog): void
    {
        $this->writes[] = ['upsert', $this->transactions->inTransaction];
        $this->geography = $catalog;
    }

    public function replaceWith(SubjectCatalog $catalog): void
    {
        $this->writes[] = ['replaceWith', $this->transactions->inTransaction];
        $this->subjects = $catalog;
    }
}
