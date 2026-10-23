<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application;

use AulaX\Workspace\Domain\Capabilities;

/**
 * The signed-in person, as the layout and the home screen see them: what
 * they can do here, and in how many institutions they are active.
 */
final readonly class Viewer
{
    public function __construct(
        public Capabilities $capabilities,
        public int $activeInstitutions,
    ) {}
}
