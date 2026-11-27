<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

use DateTimeImmutable;

final readonly class ListAuditEntriesQuery
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?int $actorId = null,
        public ?string $eventType = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public int $page = 1,
    ) {}
}
