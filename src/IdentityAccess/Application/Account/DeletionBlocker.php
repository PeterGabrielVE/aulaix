<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

/**
 * Something that must be resolved before an account can be deleted
 * (IDG-R08.2 – R08.4). An extension point: SPEC-009 (only legal guardian of
 * a student) and SPEC-008 (only teacher of an assignment) register theirs.
 * Takes the account's id as a plain int, so other modules implement it
 * without this module's domain (A-06).
 */
interface DeletionBlocker
{
    /**
     * The container tag a module puts its blocker under.
     */
    public const string TAG = 'identity.deletion_blockers';

    /**
     * Why the account can not be deleted yet; empty when nothing blocks it.
     *
     * @return list<string>
     */
    public function reasonsFor(int $accountId): array;
}
