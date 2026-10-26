<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Applies a resolution, all or nothing, with the same rules as the console
 * command (SPEC-014 PLT-R06.4, R06.5, IDG-NF07).
 */
final readonly class ResolveCaseHandler extends CaseResolver
{
    /**
     * @param  list<int>  $accountIds
     * @return array{decision: string, survivorId: ?int, affectedIds: list<int>, applied: bool}
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(array $accountIds, string $decision, string $reason, ?string $verificationRef): array
    {
        return $this->resolve($accountIds, $decision, $reason, $verificationRef, dryRun: false);
    }
}
