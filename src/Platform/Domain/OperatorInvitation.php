<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use DateTimeImmutable;

/**
 * A one-use link to join the panel or enrol a TOTP again (PLT-R03.1, R03.2,
 * R03.7). Lasts 48 hours. Only the SHA-256 of its token is kept; the token
 * itself leaves once, in the email (V4). A newer link, or the operator's
 * deactivation, revokes it.
 */
final class OperatorInvitation
{
    private const string LIFETIME = '+48 hours';

    private function __construct(
        public readonly ?int $id,
        public readonly OperatorId $operatorId,
        public readonly InvitationPurpose $purpose,
        public readonly string $tokenHash,
        public readonly DateTimeImmutable $expiresAt,
        public private(set) ?DateTimeImmutable $usedAt,
        public private(set) bool $revoked,
    ) {}

    public static function issue(OperatorId $operatorId, InvitationPurpose $purpose, string $tokenHash, DateTimeImmutable $now): self
    {
        return new self(null, $operatorId, $purpose, $tokenHash, $now->modify(self::LIFETIME), null, false);
    }

    public static function reconstitute(
        int $id,
        OperatorId $operatorId,
        InvitationPurpose $purpose,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
        bool $revoked,
    ): self {
        return new self($id, $operatorId, $purpose, $tokenHash, $expiresAt, $usedAt, $revoked);
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && ! $this->revoked && $now < $this->expiresAt;
    }

    /**
     * @throws OperatorInvitationRejected
     */
    public function use(DateTimeImmutable $now): void
    {
        if (! $this->isUsableAt($now)) {
            throw OperatorInvitationRejected::create();
        }

        $this->usedAt = $now;
    }

    public function revoke(): void
    {
        $this->revoked = true;
    }
}
