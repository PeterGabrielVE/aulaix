<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Invitation;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use DateTimeImmutable;

/**
 * An institution's pending offer to an email to join it with some roles
 * (ADR 0004). The membership is only created when it is accepted. Only the
 * hash of its token is kept (IDG-NF04); correcting or resending it issues a
 * new token, so the previous link stops working (IDG-R04.7, IDG-R04.8).
 */
final readonly class Invitation
{
    public const LIFETIME_DAYS = 7;

    private function __construct(
        public ?int $id,
        public InstitutionId $institutionId,
        public EmailAddress $email,
        public PersonName $name,
        public RoleSet $roles,
        public string $tokenHash,
        public DateTimeImmutable $expiresAt,
        public InvitationStatus $status,
        public ?AccountId $invitedBy,
        public ?AccountId $acceptedBy,
    ) {}

    public static function issue(
        InstitutionId $institutionId,
        EmailAddress $email,
        PersonName $name,
        RoleSet $roles,
        ?AccountId $invitedBy,
        DateTimeImmutable $now,
    ): IssuedInvitation {
        if ($roles->isEmpty()) {
            throw BusinessRuleViolation::onField('roles', 'Indica al menos un rol.');
        }

        $token = self::newToken();

        return new IssuedInvitation(
            new self(null, $institutionId, $email, $name, $roles, self::hash($token), $now->modify('+'.self::LIFETIME_DAYS.' days'), InvitationStatus::Pending, $invitedBy, null),
            $token,
        );
    }

    public static function reconstitute(
        int $id,
        InstitutionId $institutionId,
        EmailAddress $email,
        PersonName $name,
        RoleSet $roles,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        InvitationStatus $status,
        ?AccountId $invitedBy,
        ?AccountId $acceptedBy,
    ): self {
        return new self($id, $institutionId, $email, $name, $roles, $tokenHash, $expiresAt, $status, $invitedBy, $acceptedBy);
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->status === InvitationStatus::Pending && $now <= $this->expiresAt;
    }

    /**
     * Invitations sent before SPEC-004 kept the bcrypt hash of Laravel's
     * password broker; they still work until they expire (IDG-R09.6).
     */
    public function matches(#[\SensitiveParameter] string $token): bool
    {
        if (str_starts_with($this->tokenHash, '$2y$')) {
            return password_verify($token, $this->tokenHash);
        }

        return hash_equals($this->tokenHash, self::hash($token));
    }

    /**
     * @throws InvitationNotUsable
     */
    public function accept(#[\SensitiveParameter] string $token, AccountId $acceptedBy, DateTimeImmutable $now): self
    {
        if (! $this->isUsable($now) || ! $this->matches($token)) {
            throw InvitationNotUsable::create();
        }

        return new self($this->id, $this->institutionId, $this->email, $this->name, $this->roles, $this->tokenHash, $this->expiresAt, InvitationStatus::Accepted, $this->invitedBy, $acceptedBy);
    }

    /**
     * Accepted by an account that logged in with the invited email: logging
     * in already proves they receive mail there, so no token is needed
     * (IDG-R04.5).
     *
     * @throws InvitationNotUsable
     */
    public function acceptBy(AccountId $acceptedBy, DateTimeImmutable $now): self
    {
        if (! $this->isUsable($now)) {
            throw InvitationNotUsable::create();
        }

        return new self($this->id, $this->institutionId, $this->email, $this->name, $this->roles, $this->tokenHash, $this->expiresAt, InvitationStatus::Accepted, $this->invitedBy, $acceptedBy);
    }

    public function correct(PersonName $name, EmailAddress $email, DateTimeImmutable $now): IssuedInvitation
    {
        $this->assertPending();

        return $this->renewed($name, $email, $now);
    }

    public function resend(DateTimeImmutable $now): IssuedInvitation
    {
        $this->assertPending();

        return $this->renewed($this->name, $this->email, $now);
    }

    public function cancel(): self
    {
        $this->assertPending();

        return new self($this->id, $this->institutionId, $this->email, $this->name, $this->roles, $this->tokenHash, $this->expiresAt, InvitationStatus::Cancelled, $this->invitedBy, null);
    }

    private function renewed(PersonName $name, EmailAddress $email, DateTimeImmutable $now): IssuedInvitation
    {
        $token = self::newToken();

        return new IssuedInvitation(
            new self($this->id, $this->institutionId, $email, $name, $this->roles, self::hash($token), $now->modify('+'.self::LIFETIME_DAYS.' days'), InvitationStatus::Pending, $this->invitedBy, null),
            $token,
        );
    }

    private function assertPending(): void
    {
        if ($this->status !== InvitationStatus::Pending) {
            throw BusinessRuleViolation::onField('email', 'Esta invitación ya no está pendiente.');
        }
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }
}
