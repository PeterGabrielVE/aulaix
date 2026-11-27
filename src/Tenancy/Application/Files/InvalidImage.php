<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Files;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The uploaded file is not a PNG or JPEG image of up to 1 MB (INS-R04.3, R04.4).
 */
final class InvalidImage extends BusinessRuleViolation
{
    public static function rejected(): self
    {
        return self::onField('file', 'Sube una imagen PNG o JPEG de hasta 1 MB.');
    }
}
