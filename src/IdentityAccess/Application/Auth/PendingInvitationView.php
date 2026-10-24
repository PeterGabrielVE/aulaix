<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use DateTimeImmutable;

/**
 * An invitation waiting for the person, as their welcome screen shows it
 * (IDG-R03.6).
 */
final readonly class PendingInvitationView
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $id,
        public string $institution,
        public string $subdomain,
        public array $roles,
        public DateTimeImmutable $expiresAt,
    ) {}
}
