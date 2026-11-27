<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Events;

use AulaX\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * An invitation turned into a member of an institution, by a new account or
 * an existing one. Published after the commit, for other modules: SPEC-009
 * links the guardian the invitation was sent for (EST-R08.1).
 */
final readonly class MemberJoined implements DomainEvent
{
    public function __construct(
        public int $institutionId,
        public int $accountId,
        public int $invitationId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
