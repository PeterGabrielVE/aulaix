<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

/**
 * The institutions, from Tenancy (SPEC-014 PLT-R04.1, R04.2, R05).
 */
interface InstitutionDirectory
{
    /**
     * @return array{items: list<array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}>, total: int, page: int, perPage: int}
     */
    public function page(?string $search, ?string $status, int $page): array;

    /**
     * @return ?array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}
     */
    public function one(int $id): ?array;

    /**
     * @return bool Whether the status changed.
     */
    public function setActive(int $id, bool $active): bool;
}
