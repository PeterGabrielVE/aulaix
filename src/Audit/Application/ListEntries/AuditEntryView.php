<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

use AulaX\Audit\Application\FieldChange;
use DateTimeImmutable;

/**
 * One entry of the audit log, as read back.
 */
final readonly class AuditEntryView
{
    /**
     * @param  ?string  $actorName  Null when the actor's account no longer exists (AUD-R05.4).
     * @param  list<FieldChange>  $changes
     */
    public function __construct(
        public int $id,
        public DateTimeImmutable $occurredAt,
        public string $eventType,
        public string $subjectType,
        public string $subjectId,
        public ?int $actorId,
        public ?string $actorName,
        public string $origin,
        public ?string $ip,
        public ?string $operator,
        public array $changes,
    ) {}
}
