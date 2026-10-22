<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

/**
 * The state of the school year as the calendar (SPEC-007) tells it, which
 * decides what of its structure can change (EAC-R07).
 */
enum YearStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Closed = 'closed';
}
