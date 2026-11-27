<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Correction;

enum CorrectionStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
}
