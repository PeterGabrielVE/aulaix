<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Correction;

use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\NotFound;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Domain\Correction\CorrectionRequestId;
use AulaX\Students\Domain\Correction\CorrectionRequestRepository;

/**
 * A manager marks a correction request attended, which its guardian then
 * sees (EST-R08.5).
 */
final readonly class ResolveCorrectionHandler
{
    public function __construct(
        private CorrectionRequestRepository $corrections,
        private StudentPermissions $permissions,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws NotFound
     * @throws BusinessRuleViolation When it was already attended.
     */
    public function __invoke(int $correctionId): void
    {
        StudentAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($correctionId) {
            $request = ($correctionId > 0 ? $this->corrections->byId(CorrectionRequestId::fromInt($correctionId)) : null)
                ?? throw new NotFound('No encontramos esa solicitud de corrección.');

            $request->resolve($this->clock->now());
            $this->corrections->save($request);
        });
    }
}
