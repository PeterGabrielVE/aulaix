<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

final readonly class InviteMemberCommand
{
    /**
     * @param  int  $actorId  The manager sending the invitation.
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $actorId,
        public string $email,
        public string $name,
        public array $roles,
    ) {}
}
