<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\ShowStructure;

final readonly class SectionView
{
    /**
     * @param  array<int, int>  $teachers  Subject id => account id.
     */
    public function __construct(
        public int $id,
        public string $grade,
        public string $gradeName,
        public string $letter,
        public int $capacity,
        public ?string $capacityReason,
        public ?int $guideAccountId,
        public array $teachers,
    ) {}
}
