<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use DateTimeImmutable;

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

    /**
     * The account whose pending email change the token verifies (IDG-R05.3).
     */
    public function byPendingEmailToken(#[\SensitiveParameter] string $token): ?Account;

    /**
     * Accounts whose grace period ended (IDG-R08.10, R08.11).
     *
     * @return list<Account>
     */
    public function dueForClosing(DateTimeImmutable $now): array;

    /**
     * Only for the accounts merged away by a consolidation (ADR 0006 B).
     */
    public function delete(AccountId $id): void;
}
