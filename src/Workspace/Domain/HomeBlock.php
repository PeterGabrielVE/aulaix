<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * The parts a home screen can carry, in the order they are shown
 * (NAV-R04.1): pending tasks, indicators, personal content, the recent log
 * and the shortcuts. The student notice goes first.
 */
enum HomeBlock: string
{
    case StudentNotice = 'student_notice';
    case PendingYear = 'pending_year';
    case PendingStructure = 'pending_structure';
    case PendingStudents = 'pending_students';
    case PendingInvitations = 'pending_invitations';
    case PendingInstitutionData = 'pending_institution_data';
    case CalendarIndicators = 'calendar';
    case StructureIndicators = 'structure';
    case StudentIndicators = 'students';
    case StaffIndicators = 'staff';
    case MyClasses = 'my_classes';
    case MyStudents = 'my_students';
    case RecentActivity = 'recent_activity';
    case Shortcuts = 'shortcuts';

    public function isPending(): bool
    {
        return str_starts_with($this->value, 'pending_');
    }
}
