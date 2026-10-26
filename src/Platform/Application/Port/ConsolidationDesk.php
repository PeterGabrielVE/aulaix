<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The doubtful consolidation cases, through IdentityAccess's own use cases
 * (SPEC-014 PLT-R06, IDG-NF07): the panel decides nothing the console could
 * not.
 */
interface ConsolidationDesk
{
    /**
     * @return list<array{email: string, accounts: list<array{id: int, name: string, institutions: list<string>}>}>
     */
    public function cases(): array;

    /**
     * @param  list<int>  $accountIds
     * @return array{decision: string, survivorId: ?int, affectedIds: list<int>, applied: bool}
     *
     * @throws BusinessRuleViolation The case was refused; nothing changed.
     */
    public function resolve(string $operatorEmail, array $accountIds, string $decision, string $reason, ?string $verificationRef, bool $dryRun): array;
}
