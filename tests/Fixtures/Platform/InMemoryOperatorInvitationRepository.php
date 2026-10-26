<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use DateTimeImmutable;

/**
 * Links in memory, with the same rules as the Eloquent repository: a new
 * link revokes the operator's pending ones, and consuming is one-shot.
 */
final class InMemoryOperatorInvitationRepository implements OperatorInvitationRepository
{
    /** @var array<int, OperatorInvitation> */
    public array $invitations = [];

    public function issue(OperatorInvitation $invitation): int
    {
        $this->revokeAllOf($invitation->operatorId);
        $id = count($this->invitations) + 1;
        $this->invitations[$id] = OperatorInvitation::reconstitute(
            $id, $invitation->operatorId, $invitation->purpose, $invitation->tokenHash, $invitation->expiresAt, null, false,
        );

        return $id;
    }

    public function byTokenHash(string $tokenHash): ?OperatorInvitation
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->tokenHash === $tokenHash) {
                return clone $invitation;
            }
        }

        return null;
    }

    public function consume(OperatorInvitation $invitation, DateTimeImmutable $now): bool
    {
        $stored = $this->invitations[$invitation->id] ?? null;

        if ($stored === null || ! $stored->isUsableAt($now)) {
            return false;
        }

        $stored->use($now);

        return true;
    }

    public function revokeAllOf(OperatorId $operator): void
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->operatorId->equals($operator) && $invitation->usedAt === null) {
                $invitation->revoke();
            }
        }
    }
}
