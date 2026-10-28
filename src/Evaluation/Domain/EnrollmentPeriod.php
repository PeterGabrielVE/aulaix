<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * When a student was in a section of a grade (SPEC-010): from the day it
 * started until the day it ended, that day excluded. An activity applies to
 * a student only if it was in the activity's section on the activity's
 * date (EVA-R02.7, R02.8); the rest are NA, worked out, never stored.
 */
final readonly class EnrollmentPeriod
{
    public function __construct(
        public int $sectionId,
        public string $grade,
        public DateTimeImmutable $startsOn,
        public ?DateTimeImmutable $endsOn,
    ) {}

    public function covers(DateTimeImmutable $date): bool
    {
        return $date >= $this->startsOn && ($this->endsOn === null || $date < $this->endsOn);
    }

    /**
     * @param  list<self>  $periods
     */
    public static function applies(array $periods, int $sectionId, DateTimeImmutable $date): bool
    {
        foreach ($periods as $period) {
            if ($period->sectionId === $sectionId && $period->covers($date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the student is in the section today: its grades there can
     * still change; once it left (a change of grade above all), they are
     * kept for reading only (EVA-R09.3).
     *
     * @param  list<self>  $periods
     */
    public static function currentIn(array $periods, int $sectionId, DateTimeImmutable $today): bool
    {
        foreach ($periods as $period) {
            if ($period->sectionId === $sectionId && $period->covers($today)) {
                return true;
            }
        }

        return false;
    }
}
