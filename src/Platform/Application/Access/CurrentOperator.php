<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

/**
 * Who is using the panel right now: what its pages may show (S-10).
 */
final readonly class CurrentOperator
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
    ) {}
}
