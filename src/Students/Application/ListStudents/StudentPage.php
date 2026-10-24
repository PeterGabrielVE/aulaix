<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ListStudents;

final readonly class StudentPage
{
    /**
     * @param  list<StudentListItem>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
