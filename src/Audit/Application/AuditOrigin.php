<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

use LogicException;

/**
 * Where an audited change came from (AUD-R01.3).
 */
final readonly class AuditOrigin
{
    private function __construct(
        public string $kind,
        public ?string $ip = null,
        public ?string $operator = null,
    ) {}

    public static function web(?string $ip): self
    {
        return new self('web', ip: $ip);
    }

    public static function console(string $operator): self
    {
        if (trim($operator) === '') {
            throw new LogicException('A console change needs the operator who ran it.');
        }

        return new self('console', operator: $operator);
    }

    /**
     * SPEC-014: a platform operator in the panel, named by its id.
     */
    public static function platform(?string $ip, int $operatorId): self
    {
        return new self('platform', ip: $ip, operator: "operador #{$operatorId}");
    }

    public static function system(): self
    {
        return new self('system');
    }
}
