<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * The capacity of a section and its current enrollments (MAT-R02): up to
 * the capacity anyone is admitted; over it, only with a reason.
 */
final readonly class Occupancy
{
    public const int REASON_MAX_LENGTH = 500;

    public function __construct(
        public int $capacity,
        public int $enrolled,
    ) {}

    /**
     * The reason to keep for going over the capacity, or null when there is
     * room.
     *
     * @throws OverCapacity
     */
    public function admit(?string $reason): ?string
    {
        if ($this->enrolled < $this->capacity) {
            return null;
        }

        $reason = trim((string) $reason);

        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw OverCapacity::of($this);
        }

        return $reason;
    }

    public function isOver(): bool
    {
        return $this->enrolled > $this->capacity;
    }
}
