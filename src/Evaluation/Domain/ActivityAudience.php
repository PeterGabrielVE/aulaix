<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * Who an activity's grades are for (ADR 0013): whoever is in its section
 * or group today, and to whom it applies on its date.
 */
interface ActivityAudience
{
    public function isCurrent(int $studentId, DateTimeImmutable $today): bool;

    public function applies(int $studentId, DateTimeImmutable $date): bool;

    /**
     * The reason a student who is not current takes no grade.
     */
    public function notCurrent(int $studentId): GradeNotValid;
}
