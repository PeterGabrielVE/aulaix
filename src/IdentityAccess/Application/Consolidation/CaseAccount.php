<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

final readonly class CaseAccount
{
    /**
     * @param  list<string>  $institutions  Names of the institutions it is a member of.
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $institutions,
    ) {}
}
