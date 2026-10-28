<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use DateTimeImmutable;

/**
 * A student's age at the cutoff date of a school year, against the ages of
 * a grade (MAT-R03.1 – R03.3, R03.7). The cutoff is a month and day of the
 * calendar year the school year starts in ("12-31").
 */
final readonly class AgeCheck
{
    private function __construct(
        public int $age,
        public AgeFit $fit,
        public ?AgeRule $rule,
    ) {}

    public static function of(DateTimeImmutable $birthDate, DateTimeImmutable $schoolYearStart, string $cutoff, ?AgeRule $rule): self
    {
        $cutoffDate = new DateTimeImmutable($schoolYearStart->format('Y').'-'.$cutoff);
        $age = $birthDate > $cutoffDate ? 0 : $birthDate->diff($cutoffDate)->y;

        $fit = match (true) {
            $rule === null => AgeFit::Unchecked,
            $age === $rule->regular => AgeFit::Regular,
            $age >= $rule->min && $age <= $rule->max => AgeFit::Warning,
            default => AgeFit::OutOfRange,
        };

        return new self($age, $fit, $rule);
    }

    /**
     * The exception to keep with the enrollment: only out of range, where it
     * is required.
     *
     * @throws AgeOutOfRange
     */
    public function admit(?AgeException $exception): ?AgeException
    {
        if ($this->fit !== AgeFit::OutOfRange || $this->rule === null) {
            return null;
        }

        return $exception ?? throw AgeOutOfRange::of($this->age, $this->rule);
    }
}
