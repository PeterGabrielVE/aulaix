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
