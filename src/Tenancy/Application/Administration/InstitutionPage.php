<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Administration;

/**
 * One page of every institution, for the platform (SPEC-014 PLT-R04.1).
 */
final readonly class InstitutionPage
{
    /**
     * @param  list<array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
