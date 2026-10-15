<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

final readonly class CorrectInvitationCommand
{
    public function __construct(
        public int $invitationId,
        public string $name,
        public string $email,
    ) {}
}
