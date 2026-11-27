<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

/**
 * Where a grade sits for the capacity cap (EAC-R02.8): Inicial splits into
 * Maternal and Preescolar.
 */
enum Stage: string
{
    case Maternal = 'maternal';
    case Preescolar = 'preescolar';
    case Primaria = 'primaria';
    case MediaGeneral = 'media_general';

    /**
     * Most students a section of this stage takes without a stated reason.
     */
    public function capacityCap(): int
    {
        return match ($this) {
            self::Maternal => 15,
            self::Preescolar => 25,
            self::Primaria => 35,
            self::MediaGeneral => 40,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Maternal => 'Maternal',
            self::Preescolar => 'Preescolar',
            self::Primaria => 'Primaria',
            self::MediaGeneral => 'Media General',
        };
    }
}
