<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * Why an enrollment stopped being current (MAT-R04.6).
 */
enum EndReason: string
{
    case SectionChange = 'section_change';
    case GradeChange = 'grade_change';
    case Withdrawal = 'withdrawal';
    case Transfer = 'transfer';
    case Annulled = 'annulled';

    public function label(): string
    {
        return match ($this) {
            self::SectionChange => 'Cambio de sección',
            self::GradeChange => 'Cambio de grado',
            self::Withdrawal => 'Retiro',
            self::Transfer => 'Traslado',
            self::Annulled => 'Anulada',
        };
    }
}
