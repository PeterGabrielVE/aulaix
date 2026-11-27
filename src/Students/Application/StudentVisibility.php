<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Students\Application\Port\StudentVisibilityRule;

/**
 * Every registered StudentVisibilityRule, asked together: the students
 * seen are those every rule lets through. Asked once per instance, which
 * lives as long as the query that uses it: asking again, e.g. once per row,
 * costs nothing (SPEC-010 MAT-NF05).
 */
final class StudentVisibility
{
    private bool $known = false;

    /** @var ?list<int> */
    private ?array $visible = null;

    /**
     * @param  iterable<StudentVisibilityRule>  $rules
     */
    public function __construct(private readonly iterable $rules) {}

    /**
     * @return ?list<int> Null when every student is seen.
     */
    public function visibleStudents(): ?array
    {
        if (! $this->known) {
            $this->visible = $this->workOut();
            $this->known = true;
        }

        return $this->visible;
    }

    /**
     * @return ?list<int>
     */
    private function workOut(): ?array
    {
        $visible = null;

        foreach ($this->rules as $rule) {
            $ids = $rule->visibleStudents();

            if ($ids !== null) {
                $visible = $visible === null ? $ids : array_intersect($visible, $ids);
            }
        }

        if ($visible === null) {
            return null;
        }

        $visible = array_values(array_unique($visible));
        sort($visible);

        return $visible;
    }

    public function sees(int $studentId): bool
    {
        $visible = $this->visibleStudents();

        return $visible === null || in_array($studentId, $visible, true);
    }
}
