<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application\Geography;

/**
 * Read side of the geography catalog, for other modules: the location
 * selectors (state → municipality → parish) and checking a parish.
 */
interface GeographyDirectory
{
    /**
     * @return list<array{id: int, name: string}> By name.
     */
    public function states(): array;

    /**
     * @return list<array{id: int, name: string}> By name.
     */
    public function municipalitiesOf(int $stateId): array;

    /**
     * @return list<array{id: int, name: string}> By name.
     */
    public function parishesOf(int $municipalityId): array;

    public function stateExists(int $stateId): bool;

    public function parishExists(int $parishId): bool;

    /**
     * Where the parish is, to show the selectors already chosen.
     *
     * @return array{state_id: int, municipality_id: int, parish_id: int}|null
     */
    public function locationOf(int $parishId): ?array;
}
