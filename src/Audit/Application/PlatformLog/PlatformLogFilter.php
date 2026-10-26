<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\PlatformLog;

use DateTimeImmutable;

/**
 * Optional filters of the platform log (SPEC-014 PLT-R07.2). Null means "do
 * not filter by this".
 */
final readonly class PlatformLogFilter
{
    /**
     * @param  ?string  $operator  As recorded: «operador #id» or a console operator.
     * @param  ?string  $eventTypePrefix  «platform.institution» matches every event of it.
     */
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?string $operator = null,
        public ?string $eventTypePrefix = null,
    ) {}
}
