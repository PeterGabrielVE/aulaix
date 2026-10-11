<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ResolveTenant;

use AulaX\Shared\Domain\NotFound;

final class InstitutionNotFound extends NotFound
{
    public static function atSubdomain(): self
    {
        return new self('Institución no encontrada.');
    }
}
