<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

enum TermStatus: string
{
    /** Not opened yet, or set back after the previous term was reopened. */
    case Pending = 'pending';

    /** The term in course: its grades can change (CAL-R05.1). */
    case Open = 'open';

    case Closed = 'closed';
}
