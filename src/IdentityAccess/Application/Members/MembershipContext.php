<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

/**
 * Who a person is in the open institution (SPEC-013 NAV-R02.4 – R02.6): their
 * roles, the one that leads, and in how many institutions they are active.
 */
final readonly class MembershipContext
{
    /**
     * @param  list<string>  $roles  Base roles first, by priority.
     */
    public function __construct(
        public array $roles,
        public ?string $primaryRole,
        public int $activeInstitutions,
    ) {}
}
