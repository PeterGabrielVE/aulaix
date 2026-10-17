<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;
use LogicException;

/**
 * A person's global identity (ADR 0004): one email, one password, one name,
 * shared by all the institutions they belong to. Only its owner changes it
 * (IDG-R05).
 *
 * The stored password hash is never loaded into the domain: the account only
 * carries a new one ($newPassword) when it has just been set, for the
 * repository to persist.
 */
final readonly class Account
{
    /**
     * Calendar days between asking to delete the account and closing it
     * (product decision Q7).
     */
    public const DELETION_GRACE_DAYS = 30;

    private function __construct(
        public ?AccountId $id,
        public EmailAddress $email,
        public PersonName $name,
        public AccountState $state,
        public ?ConsolidationState $consolidation,
        public ?DateTimeImmutable $emailVerifiedAt,
        public ?EmailChangeRequest $pendingEmailChange = null,
        public ?DateTimeImmutable $deletionRequestedAt = null,
        public ?DateTimeImmutable $deletionDueAt = null,
        public ?HashedPassword $newPassword = null,
    ) {}

    /**
     * A new account created by accepting an invitation: the link itself
     * proves the person receives mail at that address (IDG-R04.4).
     */
    public static function register(EmailAddress $email, PersonName $name, HashedPassword $password, DateTimeImmutable $now): self
    {
        return new self(null, $email, $name, AccountState::Active, null, $now, newPassword: $password);
    }

    public static function reconstitute(
        AccountId $id,
        EmailAddress $email,
        PersonName $name,
        AccountState $state,
        ?ConsolidationState $consolidation,
        ?DateTimeImmutable $emailVerifiedAt,
        ?EmailChangeRequest $pendingEmailChange = null,
        ?DateTimeImmutable $deletionRequestedAt = null,
        ?DateTimeImmutable $deletionDueAt = null,
    ): self {
        return new self($id, $email, $name, $state, $consolidation, $emailVerifiedAt, $pendingEmailChange, $deletionRequestedAt, $deletionDueAt);
    }

    /**
     * The whole account's password, for every institution (IDG-R06.1).
     */
    public function changePassword(HashedPassword $password): self
    {
        return $this->with(newPassword: $password);
    }

    /**
     * IDG-R05.1.
     */
    public function changeName(PersonName $name): self
    {
        return $this->with(name: $name);
    }

    /**
     * The current email stays until the new one is verified (IDG-R05.2). An
     * email shared by several historical accounts is consolidated first.
     */
    public function requestEmailChange(EmailChangeRequest $request): self
    {
        if ($this->isUnderConsolidation()) {
            throw BusinessRuleViolation::onField('email', 'Primero unifica tus cuentas: tu correo todavía tiene una cuenta en cada plantel.');
        }

        if ($request->email->equals($this->email)) {
            throw BusinessRuleViolation::onField('email', 'Ese ya es tu correo.');
        }

        return $this->with(pendingEmailChange: $request);
    }

    /**
     * IDG-R05.3: the new email becomes the account's, already verified.
     *
     * @throws EmailChangeNotUsable
     */
    public function confirmEmailChange(#[\SensitiveParameter] string $token, DateTimeImmutable $now): self
    {
        if ($this->pendingEmailChange === null || ! $this->pendingEmailChange->isUsable($token, $now)) {
            throw EmailChangeNotUsable::create();
        }

        return $this->with(email: $this->pendingEmailChange->email, emailVerifiedAt: $now, pendingEmailChange: null);
    }

    /**
     * The owner asks to delete the account (IDG-R08.5): it waits 30 calendar
     * days in pending_deletion, and any pending email change is dropped.
     */
    public function requestDeletion(DateTimeImmutable $now): self
    {
        if (! $this->isActive()) {
            throw BusinessRuleViolation::onField('confirmation', 'Solo se puede pedir la eliminación de una cuenta activa.');
        }

        return $this->with(
            state: AccountState::PendingDeletion,
            pendingEmailChange: null,
            deletionRequestedAt: $now,
            deletionDueAt: $now->modify('+'.self::DELETION_GRACE_DAYS.' days'),
        );
    }

    /**
     * IDG-R08.9.
     */
    public function cancelDeletion(): self
    {
        if ($this->state !== AccountState::PendingDeletion) {
            throw BusinessRuleViolation::onField('confirmation', 'Esta cuenta no tiene una eliminación pendiente.');
        }

        return $this->with(state: AccountState::Active, deletionRequestedAt: null, deletionDueAt: null);
    }

    public function isDueForClosing(DateTimeImmutable $now): bool
    {
        return $this->state === AccountState::PendingDeletion
            && $this->deletionDueAt !== null
            && $this->deletionDueAt <= $now;
    }

    /**
     * Something still blocks the deletion at the end of the grace period
     * (IDG-R08.10): try again later.
     */
    public function postponeDeletion(DateTimeImmutable $until): self
    {
        $this->assertPendingDeletion();

        return $this->with(deletionDueAt: $until);
    }

    /**
     * End of the grace period (IDG-R08.11): no login ever again, and a
     * password nobody knows replaces the old one. No data is erased.
     */
    public function close(HashedPassword $unusablePassword): self
    {
        $this->assertPendingDeletion();

        return $this->with(state: AccountState::Closed, newPassword: $unusablePassword);
    }

    /**
     * The account that survives a consolidation: a regular account again,
     * the only one left with its email (ADR 0006 B).
     */
    public function consolidated(): self
    {
        if (! $this->isUnderConsolidation()) {
            throw new LogicException('Only an account awaiting consolidation can be consolidated.');
        }

        return $this->with(consolidation: null);
    }

    /**
     * Support decided these accounts belong to different people: they stay
     * apart for good, each password opening its own institution (IDG-R11.2).
     */
    public function keptSeparate(): self
    {
        if ($this->consolidation !== ConsolidationState::Review) {
            throw new LogicException('Only an account under review can be kept separate.');
        }

        return $this->with(consolidation: ConsolidationState::Separated);
    }

    public function isActive(): bool
    {
        return $this->state === AccountState::Active;
    }

    /**
     * A historical account sharing its email with others (ADR 0006 B).
     */
    public function isUnderConsolidation(): bool
    {
        return $this->consolidation !== null;
    }

    private function assertPendingDeletion(): void
    {
        if ($this->state !== AccountState::PendingDeletion) {
            throw new LogicException('Only an account pending deletion can be closed or postponed.');
        }
    }

    /**
     * A copy with some properties changed.
     */
    private function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
