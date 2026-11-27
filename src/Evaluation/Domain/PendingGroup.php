<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use InvalidArgumentException;

/**
 * The group of a pending subject in a school year (RVP-R03): one per subject
 * and grade of origin of Media General, with the teacher who grades it, or
 * none yet (R03.4). Its members are not kept here: they follow from the
 * results of the years before.
 */
final class PendingGroup
{
    private function __construct(
        private ?int $id,
        private readonly int $schoolYearId,
        private readonly int $subjectId,
        private readonly string $originGrade,
        private ?int $teacherAccountId,
    ) {}

    public static function open(int $schoolYearId, int $subjectId, string $originGrade): self
    {
        if (! str_starts_with($originGrade, 'media_')) {
            throw new InvalidArgumentException('A pending subject comes from a grade of Media General.');
        }

        return new self(null, $schoolYearId, $subjectId, $originGrade, null);
    }

    public static function reconstitute(int $id, int $schoolYearId, int $subjectId, string $originGrade, ?int $teacherAccountId): self
    {
        return new self($id, $schoolYearId, $subjectId, $originGrade, $teacherAccountId);
    }

    /**
     * @return ?array{0: ?int, 1: ?int} The teacher before and after, or null when nothing changed.
     */
    public function assignTeacher(?int $accountId): ?array
    {
        if ($accountId === $this->teacherAccountId) {
            return null;
        }

        $before = $this->teacherAccountId;
        $this->teacherAccountId = $accountId;

        return [$before, $accountId];
    }

    public function identify(int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolYearId(): int
    {
        return $this->schoolYearId;
    }

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function originGrade(): string
    {
        return $this->originGrade;
    }

    public function teacherAccountId(): ?int
    {
        return $this->teacherAccountId;
    }
}
