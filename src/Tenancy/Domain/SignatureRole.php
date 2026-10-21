<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

/**
 * Whose signature it is: the two authorities that sign the institution's
 * documents (product decision, SPEC-006).
 */
enum SignatureRole: string
{
    case Director = 'director';
    case Secretary = 'secretary';
}
