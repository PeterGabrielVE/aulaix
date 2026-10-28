<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * A literal with its description (Primaria) or a descriptive report
 * (Inicial).
 */
enum MarkKind: string
{
    case Literal = 'literal';
    case Report = 'report';

    /**
     * The level whose sections take this kind of mark.
     */
    public function level(): string
    {
        return match ($this) {
            self::Literal => 'primaria',
            self::Report => 'inicial',
        };
    }
}
