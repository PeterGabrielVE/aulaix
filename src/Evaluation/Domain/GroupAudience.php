<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * The members of a group of pending subjects (SPEC-016): every activity of
 * the group applies to each of them, whatever its date.
 */
final readonly class GroupAudience implements ActivityAudience
{
    /**
     * @param  list<int>  $members  Student ids.
     */
    public function __construct(private array $members) {}

    public function isCurrent(int $studentId, DateTimeImmutable $today): bool
    {
        return in_array($studentId, $this->members, true);
    }

    public function applies(int $studentId, DateTimeImmutable $date): bool
    {
        return in_array($studentId, $this->members, true);
    }

    public function notCurrent(int $studentId): GradeNotValid
    {
        return GradeNotValid::notInGroup($studentId);
    }
}
