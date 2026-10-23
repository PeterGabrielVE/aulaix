<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * The one definition of the menu (NAV-R03): its entries in order, the group
 * of each and who sees it. An entry is shown exactly when its screen lets
 * the person in, so each condition mirrors that screen's route.
 */
enum MenuEntry: string
{
    case Home = 'home';
    case Calendar = 'calendar';
    case Structure = 'structure';
    case MyAssignments = 'my_assignments';
    case Students = 'students';
    case Transfers = 'transfers';
    case Corrections = 'corrections';
    case MyStudents = 'my_students';
    case Institution = 'institution';
    case Users = 'users';
    case Audit = 'audit';

    private const string TEACHER = 'Docente';

    public function group(): ?MenuGroup
    {
        return match ($this) {
            self::Home => null,
            self::Calendar, self::Structure, self::MyAssignments => MenuGroup::Academic,
            self::Students, self::Transfers, self::Corrections, self::MyStudents => MenuGroup::Students,
            self::Institution, self::Users, self::Audit => MenuGroup::Institution,
        };
    }

    public function isVisibleTo(Capabilities $capabilities): bool
    {
        return match ($this) {
            self::Home => true,
            self::Calendar => $capabilities->has('gestionar-calendario'),
            self::Structure => $capabilities->has('ver-estructura'),
            self::MyAssignments => $capabilities->hasRole(self::TEACHER),
            self::Students => $capabilities->has('ver-estudiantes'),
            self::Transfers, self::Corrections => $capabilities->has('gestionar-estudiantes'),
            self::MyStudents => $capabilities->isGuardian,
            self::Institution => $capabilities->has('ver-plantel'),
            self::Users => $capabilities->has('gestionar-usuarios'),
            self::Audit => $capabilities->has('ver-bitacora'),
        };
    }
}
