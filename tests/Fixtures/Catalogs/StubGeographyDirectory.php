<?php

namespace Tests\Fixtures\Catalogs;

use AulaX\Catalogs\Application\Geography\GeographyDirectory;

/**
 * A geography with the states and parishes a test names.
 */
final class StubGeographyDirectory implements GeographyDirectory
{
    /**
     * @param  list<int>  $stateIds
     * @param  list<int>  $parishIds
     */
    public function __construct(private readonly array $stateIds = [4], private readonly array $parishIds = [10]) {}

    public function states(): array
    {
        return array_map(fn (int $id) => ['id' => $id, 'name' => "Estado {$id}"], $this->stateIds);
    }

    public function municipalitiesOf(int $stateId): array
    {
        return [];
    }

    public function parishesOf(int $municipalityId): array
    {
        return [];
    }

    public function stateExists(int $stateId): bool
    {
        return in_array($stateId, $this->stateIds, true);
    }

    public function parishExists(int $parishId): bool
    {
        return in_array($parishId, $this->parishIds, true);
    }

    public function locationOf(int $parishId): ?array
    {
        return null;
    }
}
