<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use DateTimeImmutable;

interface OperatorInvitationRepository
{
    /**
     * Saves a new link and revokes the operator's pending ones (V4).
     *
     * @return int The link's id.
     */
    public function issue(OperatorInvitation $invitation): int;

    public function byTokenHash(string $tokenHash): ?OperatorInvitation;

    /**
     * Marks the link used only if it still is usable, atomically: of two
     * racing requests, one wins (V4).
     */
    public function consume(OperatorInvitation $invitation, DateTimeImmutable $now): bool;

    public function revokeAllOf(OperatorId $operator): void;
}
