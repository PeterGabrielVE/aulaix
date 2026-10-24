<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Correction;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\GuardianPortal\LinkedGuardian;
use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * The correction requests a guardian sent, with whether they were attended
 * (EST-R08.5).
 */
final readonly class GuardianCorrectionsQuery
{
    public function __construct(
        private CorrectionList $corrections,
        private GuardianRepository $guardians,
    ) {}

    /**
     * @return list<CorrectionView>
     *
     * @throws AuthorizationDenied When the account represents no student here.
     */
    public function __invoke(int $accountId): array
    {
        return $this->corrections->ofGuardian(LinkedGuardian::of($this->guardians, $accountId));
    }
}
