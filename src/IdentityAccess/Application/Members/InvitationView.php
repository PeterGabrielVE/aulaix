<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use DateTimeImmutable;

final readonly class InvitationView
{
    /**
     * @param  list<string>  $roles  In priority order (IAM-R12.2).
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $roles,
        public DateTimeImmutable $expiresAt,
    ) {}
}
