<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

final readonly class UpdateMemberCommand
{
    /**
     * @param  int  $actorId  The manager making the change.
     * @param  int  $memberId  The account whose membership changes.
     * @param  list<string>  $roles
     * @param  bool  $active  False suspends the membership; true reactivates it.
     */
    public function __construct(
        public int $actorId,
        public int $memberId,
        public array $roles,
        public bool $active,
    ) {}
}
