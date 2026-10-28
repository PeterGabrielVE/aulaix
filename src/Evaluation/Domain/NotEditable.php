<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The grades of the term can not change (EVA-R06, CAL-R05).
 */
final class NotEditable extends BusinessRuleViolation
{
    public static function termClosed(): self
    {
        return self::onField('term', 'El lapso está cerrado: sus notas no cambian.');
    }

    public static function yearClosed(): self
    {
        return self::onField('term', 'El año escolar está cerrado: sus notas no cambian.');
    }

    public static function notOpened(): self
    {
        return self::onField('term', 'El lapso aún no se abre.');
    }
}
