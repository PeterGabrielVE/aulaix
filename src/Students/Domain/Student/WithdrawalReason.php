<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

/**
 * Why a student leaves the institution (EST-R06.3). A transfer between
 * AulaX institutions is a ChangedSchool (EST-R07.5).
 */
enum WithdrawalReason: string
{
    case Moved = 'moved';
    case ChangedSchool = 'changed_school';
    case EconomicOrFamily = 'economic_or_family';
    case Other = 'other';
}
