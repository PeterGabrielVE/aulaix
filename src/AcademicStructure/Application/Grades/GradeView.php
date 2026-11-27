<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Grades;

final readonly class GradeView
{
    public function __construct(
        public string $code,
        public string $level,
        public string $name,
    ) {}
}
