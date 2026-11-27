<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ListStudents;

final readonly class StudentListItem
{
    /**
     * @param  string  $fullName  Family names first: "Pérez, Ana".
     * @param  string  $status  active or withdrawn.
     */
    public function __construct(
        public int $id,
        public string $fullName,
        public string $identificationType,
        public string $identification,
        public string $status,
    ) {}
}
