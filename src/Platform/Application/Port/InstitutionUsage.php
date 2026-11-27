<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

/**
 * One institution's figures (SPEC-014 PLT-R04.3), read within its own RLS
 * and never a person's data (PLT-R04.4, ADR 0010).
 */
interface InstitutionUsage
{
    /**
     * @return array{members: array<string, int>, activeStudents: int, lastActivity: ?string, usedBytes: int, activeYear: ?string}
     */
    public function of(int $institutionId): array;
}
