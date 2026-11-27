<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Pending;

final readonly class PendingGradesView
{
    /**
     * @param  int  $total  Missing grades, literals and reports in all.
     * @param  list<PendingGradesRow>  $rows  By section, subject and activity.
     */
    public function __construct(
        public int $total,
        public array $rows,
    ) {}
}
