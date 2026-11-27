<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Consolidation;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * What a resolution would change, without changing it (SPEC-014 PLT-R06.3,
 * IDG-R11.4).
 */
final readonly class PreviewResolutionQuery extends CaseResolver
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
        return $this->resolve($accountIds, $decision, $reason, $verificationRef, dryRun: true);
    }
}
