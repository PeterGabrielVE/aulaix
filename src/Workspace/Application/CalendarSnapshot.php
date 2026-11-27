<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application;

/**
 * The institution's calendar as the top bar and the home screen need it
 * (NAV-R02.2, R02.3, R05.2).
 */
final readonly class CalendarSnapshot
{
    /**
     * @param  ?string  $activeYearName  Null when there is no active year.
     * @param  ?int  $openTerm  Null when no term of the active year is open.
     */
    public function __construct(
        public ?string $activeYearName,
        public ?int $openTerm,
        public bool $hasPlannedYear,
    ) {}

    public function hasActiveYear(): bool
    {
        return $this->activeYearName !== null;
    }
}
