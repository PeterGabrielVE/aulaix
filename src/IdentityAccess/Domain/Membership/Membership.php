<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Membership;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;

/**
 * A person's relation with one institution (ADR 0004): its status and,
 * through the institution's roles, what they may do there. Immutable: every
 * transition returns a new membership.
 */
final readonly class Membership
{
    private function __construct(
        public ?int $id,
        public AccountId $accountId,
        public InstitutionId $institutionId,
        public MembershipStatus $status,
        public ?SuspensionReason $suspensionReason,
    ) {}

    public static function start(AccountId $accountId, InstitutionId $institutionId): self
    {
        return new self(null, $accountId, $institutionId, MembershipStatus::Active, null);
    }

    public static function reconstitute(
        int $id,
        AccountId $accountId,
        InstitutionId $institutionId,
        MembershipStatus $status,
        ?SuspensionReason $suspensionReason,
    ): self {
        return new self($id, $accountId, $institutionId, $status, $suspensionReason);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function suspend(SuspensionReason $reason): self
    {
        if (! $this->isActive()) {
            throw BusinessRuleViolation::onField('status', 'Solo se puede suspender una membresía activa.');
        }

        return $this->with(MembershipStatus::Suspended, $reason);
    }

    public function reactivate(): self
    {
        if ($this->status !== MembershipStatus::Suspended) {
            throw BusinessRuleViolation::onField('status', 'Solo se puede reactivar una membresía suspendida.');
        }

        return $this->with(MembershipStatus::Active, null);
    }

    /**
     * Reactivate only if suspended for this very reason; otherwise unchanged.
     */
    public function reactivateIfSuspendedFor(SuspensionReason $reason): self
    {
        return $this->suspensionReason === $reason ? $this->reactivate() : $this;
    }

    public function end(): self
    {
        if ($this->status === MembershipStatus::Ended) {
            return $this;
        }

        return $this->with(MembershipStatus::Ended, null);
    }

    private function with(MembershipStatus $status, ?SuspensionReason $reason): self
    {
        return new self($this->id, $this->accountId, $this->institutionId, $status, $reason);
    }
}
