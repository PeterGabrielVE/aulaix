<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class HistoryEntry
{
    /**
     * @param  string  $startsOn  Y-m-d
     * @param  ?string  $endsOn  Y-m-d
     * @param  ?string  $endReason  Its label.
     * @param  ?string  $endNote  The reason given for a change.
     * @param  ?string  $annulmentReason  Its label.
     * @param  ?string  $annulledAt  Y-m-d H:i
     */
    public function __construct(
        public int $enrollmentId,
        public int $schoolYearId,
        public string $gradeName,
        public string $letter,
        public string $startsOn,
        public ?string $endsOn,
        public ?string $endReason,
        public ?string $endNote,
        public ?string $annulmentReason,
        public ?string $annulmentNote,
        public ?int $annulledBy,
        public ?string $annulledAt,
        public bool $current,
    ) {}
}
