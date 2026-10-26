<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Consolidation;

use AulaX\IdentityAccess\Application\Consolidation\CaseAccount;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationCase;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationDecision;
use AulaX\IdentityAccess\Application\Consolidation\ListConsolidationCasesHandler;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseCommand;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler;
use AulaX\Platform\Application\Port\ConsolidationDesk;

/**
 * The cases and their resolution, through the same IdentityAccess use cases
 * as the console command (IDG-NF07, constitution A-06).
 */
final readonly class IdentityConsolidationDesk implements ConsolidationDesk
{
    public function __construct(
        private ListConsolidationCasesHandler $listCases,
        private ResolveConsolidationCaseHandler $resolveCase,
    ) {}

    public function cases(): array
    {
        return array_map(fn (ConsolidationCase $case) => [
            'email' => $case->email,
            'accounts' => array_map(fn (CaseAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'institutions' => $account->institutions,
            ], $case->accounts),
        ], ($this->listCases)());
    }

    public function resolve(string $operatorEmail, array $accountIds, string $decision, string $reason, ?string $verificationRef, bool $dryRun): array
    {
        $resolution = ($this->resolveCase)(new ResolveConsolidationCaseCommand(
            $operatorEmail,
            $accountIds,
            ConsolidationDecision::from($decision),
            $reason,
            $verificationRef,
            $dryRun,
        ));

        return [
            'decision' => $resolution->decision->value,
            'survivorId' => $resolution->survivorId,
            'affectedIds' => $resolution->affectedIds,
            'applied' => $resolution->applied,
        ];
    }
}
