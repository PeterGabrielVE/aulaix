<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

enum SchoolYearStatus: string
{
    /** Created and being prepared: not in force yet. */
    case Planned = 'planned';

    /** The year in force in the institution: one at most (CAL-R02.1). */
    case Active = 'active';

    /** Finished for good (CAL-R02.6). */
    case Closed = 'closed';
}
