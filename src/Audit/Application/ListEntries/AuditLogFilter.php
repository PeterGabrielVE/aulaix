<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

use DateTimeImmutable;

/**
 * Optional filters of the institution's audit log (AUD-R05.2). Null means
 * "do not filter by this".
 */
final readonly class AuditLogFilter
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?int $actorId = null,
        public ?string $eventType = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
    ) {}
}
