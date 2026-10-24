<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * Which blocks a home screen carries (NAV-R04 – R09). A block is included
 * only when the person may open the screen its data comes from, with the
 * same condition as that screen's menu entry (NAV-R04.2, R10.2). The
 * personal blocks of other roles and links join any home screen, each once
 * (NAV-R04.6 – R04.8).
 */
final class HomePlan
{
    private const string TEACHER = 'Docente';

    /**
     * @return list<HomeBlock> In display order.
     */
    public static function for(HomeKind $kind, Capabilities $capabilities): array
    {
        return array_values(array_filter(
            HomeBlock::cases(),
            fn (HomeBlock $block) => self::carries($kind, $capabilities, $block),
        ));
    }

    /**
     * NAV-R06: the Coordinator sees the grades it coordinates, not the
     * whole institution.
     */
    public static function scopesToCoordinatedGrades(HomeKind $kind): bool
    {
        return $kind === HomeKind::Coordinator;
    }

    private static function carries(HomeKind $kind, Capabilities $capabilities, HomeBlock $block): bool
    {
        $management = $kind->isManagement();
        $academic = $management || $kind === HomeKind::Coordinator;

        return match ($block) {
            HomeBlock::StudentNotice => $kind === HomeKind::Student,
            HomeBlock::PendingYear => $management,
            HomeBlock::PendingStructure, HomeBlock::StructureIndicators => $academic && MenuEntry::Structure->isVisibleTo($capabilities),
            HomeBlock::PendingStudents => $management && MenuEntry::Transfers->isVisibleTo($capabilities),
            HomeBlock::PendingInvitations => $kind === HomeKind::Admin && MenuEntry::Users->isVisibleTo($capabilities),
            HomeBlock::PendingInstitutionData => $kind === HomeKind::Admin && $capabilities->has('gestionar-plantel'),
            HomeBlock::CalendarIndicators => $academic,
            HomeBlock::StudentIndicators => $management && MenuEntry::Students->isVisibleTo($capabilities),
            HomeBlock::StaffIndicators => $management && MenuEntry::Users->isVisibleTo($capabilities),
            HomeBlock::MyClasses => $kind === HomeKind::Teacher || $capabilities->hasRole(self::TEACHER),
            HomeBlock::MyStudents => $kind === HomeKind::Guardian || MenuEntry::MyStudents->isVisibleTo($capabilities),
            HomeBlock::RecentActivity => $kind === HomeKind::Admin && MenuEntry::Audit->isVisibleTo($capabilities),
            HomeBlock::Shortcuts => true,
        };
    }
}
