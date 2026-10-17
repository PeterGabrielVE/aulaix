<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use DateTimeImmutable;

/**
 * A person's global identity (ADR 0004): one email, one password, one name,
 * shared by all the institutions they belong to. Only its owner changes it.
 *
 * The stored password hash is never loaded into the domain: the account only
 * carries a new one ($newPassword) when it has just been set, for the
 * repository to persist.
 */
final readonly class Account
{
    private function __construct(
        public ?AccountId $id,
        public EmailAddress $email,
        public PersonName $name,
        public AccountState $state,
        public ?ConsolidationState $consolidation,
        public ?DateTimeImmutable $emailVerifiedAt,
        public ?HashedPassword $newPassword = null,
    ) {}

    /**
     * A new account created by accepting an invitation: the link itself
     * proves the person receives mail at that address (IDG-R04.4).
     */
    public static function register(EmailAddress $email, PersonName $name, HashedPassword $password, DateTimeImmutable $now): self
    {
        return new self(null, $email, $name, AccountState::Active, null, $now, $password);
    }

    public static function reconstitute(
        AccountId $id,
        EmailAddress $email,
        PersonName $name,
        AccountState $state,
        ?ConsolidationState $consolidation,
        ?DateTimeImmutable $emailVerifiedAt,
    ): self {
        return new self($id, $email, $name, $state, $consolidation, $emailVerifiedAt);
    }

    /**
     * The whole account's password, for every institution (IDG-R06.1).
     */
    public function changePassword(HashedPassword $password): self
    {
        return new self($this->id, $this->email, $this->name, $this->state, $this->consolidation, $this->emailVerifiedAt, $password);
    }

    /**
     * The account that survives a consolidation: a regular account again,
     * the only one left with its email (ADR 0006 B).
     */
    public function consolidated(): self
    {
        if (! $this->isUnderConsolidation()) {
            throw new \LogicException('Only an account awaiting consolidation can be consolidated.');
        }

        return new self($this->id, $this->email, $this->name, $this->state, null, $this->emailVerifiedAt, $this->newPassword);
    }

    /**
     * Support decided these accounts belong to different people: they stay
     * apart for good, each password opening its own institution (IDG-R11.2).
     */
    public function keptSeparate(): self
    {
        if ($this->consolidation !== ConsolidationState::Review) {
            throw new \LogicException('Only an account under review can be kept separate.');
        }

        return new self($this->id, $this->email, $this->name, $this->state, ConsolidationState::Separated, $this->emailVerifiedAt, $this->newPassword);
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
}
