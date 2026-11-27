<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * The result of a student's school year (MAT-R06).
 */
enum Outcome: string
{
    case Promoted = 'promoted';
    case Repeats = 'repeats';
    case PromotedWithPending = 'promoted_with_pending';
    case LostByAbsence = 'lost_by_absence';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Promovido',
            self::Repeats => 'Repite',
            self::PromotedWithPending => 'Promovido con materia pendiente',
            self::LostByAbsence => 'Perdió por inasistencia',
        };
    }

    public function isPromotion(): bool
    {
        return $this === self::Promoted || $this === self::PromotedWithPending;
    }
}
