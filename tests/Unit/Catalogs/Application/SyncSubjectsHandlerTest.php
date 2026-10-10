<?php

use AulaX\Catalogs\Application\Port\SubjectSource;
use AulaX\Catalogs\Application\SyncSubjectsHandler;
use AulaX\Catalogs\Domain\EducationLevel;
use AulaX\Catalogs\Domain\InvalidCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectEntry;
use Tests\Fixtures\Catalogs\InMemoryCatalogStores;
use Tests\Fixtures\Shared\InlineTransactionManager;

function subjectSource(Closure $load): SubjectSource
{
    return new class($load) implements SubjectSource
    {
        public function __construct(private readonly Closure $load) {}

        public function load(): SubjectCatalog
        {
            return ($this->load)();
        }
    };
}

it('replaces the stored subjects with the reference catalog, inside one transaction', function () {
    $catalog = SubjectCatalog::of(
        SubjectEntry::of('INI-RAMB', 'Relación con el Ambiente', EducationLevel::Inicial),
        SubjectEntry::of('PRI-LCC', 'Lenguaje, Comunicación y Cultura', EducationLevel::Primaria),
        SubjectEntry::of('MAT', 'Matemáticas', EducationLevel::MediaGeneral),
    );
    $transactions = new InlineTransactionManager;
    $store = new InMemoryCatalogStores($transactions);

    (new SyncSubjectsHandler(subjectSource(fn () => $catalog), $store, $transactions))();

    expect($store->subjects)->toBe($catalog)
        ->and($store->writes)->toBe([['replaceWith', true]]);
});

it('writes nothing when the reference catalog is invalid', function () {
    $transactions = new InlineTransactionManager;
    $store = new InMemoryCatalogStores($transactions);
    $handler = new SyncSubjectsHandler(subjectSource(fn () => throw InvalidCatalog::duplicateCode('MAT')), $store, $transactions);

    expect(fn () => $handler())->toThrow(InvalidCatalog::class);
    expect($store->writes)->toBe([]);
});
