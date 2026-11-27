<?php

use AulaX\Catalogs\Application\Port\GeographySource;
use AulaX\Catalogs\Application\SyncGeographyHandler;
use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\Geography\MunicipalityEntry;
use AulaX\Catalogs\Domain\Geography\ParishEntry;
use AulaX\Catalogs\Domain\Geography\StateEntry;
use AulaX\Catalogs\Domain\InvalidCatalog;
use Tests\Fixtures\Catalogs\InMemoryCatalogStores;
use Tests\Fixtures\Shared\InlineTransactionManager;

function geographySource(Closure $load): GeographySource
{
    return new class($load) implements GeographySource
    {
        public function __construct(private readonly Closure $load) {}

        public function load(): GeographicCatalog
        {
            return ($this->load)();
        }
    };
}

it('drops the provisional catalog and then upserts the reference one, inside one transaction', function () {
    $catalog = GeographicCatalog::of(StateEntry::of('VE-A', 'Distrito Capital', [
        MunicipalityEntry::of('VE-A-01', 'Libertador', [ParishEntry::of('VE-A-01-01', 'Catedral')]),
    ]));
    $transactions = new InlineTransactionManager;
    $store = new InMemoryCatalogStores($transactions);

    (new SyncGeographyHandler(geographySource(fn () => $catalog), $store, $transactions))();

    expect($store->geography)->toBe($catalog)
        ->and($store->writes)->toBe([['removeLegacyPlaceholders', true], ['upsert', true]]);
});

it('writes nothing when the reference catalog is invalid', function () {
    $transactions = new InlineTransactionManager;
    $store = new InMemoryCatalogStores($transactions);
    $handler = new SyncGeographyHandler(geographySource(fn () => throw InvalidCatalog::emptyBranch('VE-A')), $store, $transactions);

    expect(fn () => $handler())->toThrow(InvalidCatalog::class);
    expect($store->writes)->toBe([]);
});
