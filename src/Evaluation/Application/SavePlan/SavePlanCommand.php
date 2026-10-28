<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\SavePlan;

use AulaX\Evaluation\Domain\ActivityInput;

final readonly class SavePlanCommand
{
    /**
     * @param  list<ActivityInput>  $activities
     * @param  bool  $confirmRemovals  Leaving out activities with grades was confirmed (EVA-R01.5).
     */
    public function __construct(
        public int $schoolYearId,
        public int $sectionId,
        public int $subjectId,
        public int $term,
        public array $activities,
        public bool $confirmRemovals,
    ) {}
}
