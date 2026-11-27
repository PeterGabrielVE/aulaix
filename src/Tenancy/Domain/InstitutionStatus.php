<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

/**
 * Only an active institution can be reached through its subdomain.
 */
enum InstitutionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
