<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\ConsolidationDesk;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The consolidation cases in memory; records every resolution asked for.
 */
final class FakeConsolidationDesk implements ConsolidationDesk
{
    /** @var list<array{email: string, accounts: list<array{id: int, name: string, institutions: list<string>}>}> */
    public array $cases = [];

    /** @var list<array{operator: string, accounts: list<int>, decision: string, reason: string, verificationRef: ?string, dryRun: bool}> */
    public array $resolutions = [];

    public function add(string $email, int ...$ids): void
    {
        $this->cases[] = [
            'email' => $email,
            'accounts' => array_map(fn (int $id) => ['id' => $id, 'name' => "Cuenta {$id}", 'institutions' => ["Plantel {$id}"]], $ids),
        ];
    }

    public function cases(): array
    {
        return $this->cases;
    }

    public function resolve(string $operatorEmail, array $accountIds, string $decision, string $reason, ?string $verificationRef, bool $dryRun): array
    {
        $this->resolutions[] = ['operator' => $operatorEmail, 'accounts' => $accountIds, 'decision' => $decision, 'reason' => $reason, 'verificationRef' => $verificationRef, 'dryRun' => $dryRun];

        if (trim($reason) === '') {
            throw new BusinessRuleViolation('Falta el motivo.');
        }

        sort($accountIds);

        return $decision === 'consolidate'
            ? ['decision' => $decision, 'survivorId' => $accountIds[0], 'affectedIds' => array_slice($accountIds, 1), 'applied' => ! $dryRun]
            : ['decision' => $decision, 'survivorId' => null, 'affectedIds' => $accountIds, 'applied' => ! $dryRun];
    }
}
