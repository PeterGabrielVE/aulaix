<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * Why an enrollment made by mistake is annulled (MAT-R14.1).
 */
enum AnnulmentReason: string
{
    case TypingError = 'typing_error';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TypingError => 'Error de digitación',
            self::Duplicate => 'Doble registro',
            self::Other => 'Otro',
        };
    }
}
