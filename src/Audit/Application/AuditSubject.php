<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

use LogicException;

/**
 * The object an audited change was made to, e.g. ('grade', 42).
 */
final readonly class AuditSubject
{
    private function __construct(
        public string $type,
        public string $id,
    ) {}

    public static function of(string $type, int|string $id): self
    {
        $id = (string) $id;

        if (trim($type) === '' || trim($id) === '') {
            throw new LogicException('An audit subject needs a type and an id.');
        }

        return new self($type, $id);
    }
}
