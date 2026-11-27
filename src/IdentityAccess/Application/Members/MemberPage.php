<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

final readonly class MemberPage
{
    /**
     * @param  list<MemberView>  $members
     */
    public function __construct(
        public array $members,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
