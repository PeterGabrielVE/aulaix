<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

final class InvalidSubdomain extends BusinessRuleViolation
{
    public static function notADnsLabel(string $subdomain): self
    {
        return self::onField('subdomain', "El subdominio «{$subdomain}» no es válido.");
    }

    public static function reserved(string $subdomain): self
    {
        return self::onField('subdomain', "El subdominio «{$subdomain}» está reservado.");
    }
}
