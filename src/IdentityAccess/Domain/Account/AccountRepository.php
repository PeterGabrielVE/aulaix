<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

interface AccountRepository
{
    public function byId(AccountId $id): ?Account;

    /**
     * Every account with this email: one for a regular account, several for
     * historical accounts not consolidated yet (ADR 0006 B).
     *
     * @return list<Account>
     */
    public function byEmail(EmailAddress $email): array;

    /**
     * Persists the account (and its new password, if set); returns it with its id.
     */
    public function save(Account $account): Account;
}
