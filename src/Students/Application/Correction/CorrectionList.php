<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Correction;

/**
 * Read side of the correction requests of the open institution.
 */
interface CorrectionList
{
    /**
     * Pending first, then newest first.
     *
     * @return list<CorrectionView>
     */
    public function all(): array;

    /**
     * The ones this guardian sent, newest first.
     *
     * @return list<CorrectionView>
     */
    public function ofGuardian(int $guardianId): array;
}
