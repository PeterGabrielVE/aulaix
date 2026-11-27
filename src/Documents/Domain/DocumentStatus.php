<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

/**
 * Current validity state of an issued document (DOC-R05.5, R06, R07.2).
 */
enum DocumentStatus: string
{
    case Valid = 'valid';
    case Expired = 'expired';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Válido',
            self::Expired => 'Vencido',
            self::Voided => 'Anulado',
        };
    }
}
