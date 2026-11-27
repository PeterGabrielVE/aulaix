<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

/**
 * An institution a person can open: they have an active membership in it.
 */
final readonly class InstitutionChoice
{
    public function __construct(
        public int $id,
        public string $name,
        public string $subdomain,
    ) {}
}
