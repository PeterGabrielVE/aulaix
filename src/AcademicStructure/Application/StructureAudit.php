<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application;

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\Section;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\FieldChange;

/**
 * The audit log entries of the structure (EAC-R11.1): sections, capacities,
 * curricula and copies, with their old and new values.
 */
final class StructureAudit
{
    public static function sectionCreated(int $schoolYearId, Section $section): AuditEvent
    {
        return AuditEvent::of('structure.section.created', self::section($section), [
            FieldChange::of('school_year_id', null, $schoolYearId),
            FieldChange::of('grade', null, $section->grade()->value),
            FieldChange::of('letter', null, $section->letter()->value),
            FieldChange::of('capacity', null, $section->capacity()->value),
            FieldChange::of('capacity_reason', null, $section->capacity()->reason),
        ]);
    }

    public static function capacityChanged(Section $section, Capacity $before): AuditEvent
    {
        return AuditEvent::of('structure.section.capacity_changed', self::section($section), [
            FieldChange::of('capacity', $before->value, $section->capacity()->value),
            FieldChange::of('capacity_reason', $before->reason, $section->capacity()->reason),
        ]);
    }

    public static function sectionRemoved(Section $section): AuditEvent
    {
        return AuditEvent::of('structure.section.removed', self::section($section), [
            FieldChange::of('grade', $section->grade()->value, null),
            FieldChange::of('letter', $section->letter()->value, null),
        ]);
    }

    /**
     * @param  list<int>  $before
     * @param  list<int>  $after
     */
    public static function curriculumChanged(int $schoolYearId, Grade $grade, array $before, array $after): AuditEvent
    {
        return AuditEvent::of('structure.curriculum.changed', self::year($schoolYearId), [
            FieldChange::of('grade', null, $grade->value),
            FieldChange::of('subjects', $before, $after),
        ]);
    }

    public static function copied(int $fromSchoolYearId, int $toSchoolYearId): AuditEvent
    {
        return AuditEvent::of('structure.year.copied', self::year($toSchoolYearId), [
            FieldChange::of('copied_from_school_year_id', null, $fromSchoolYearId),
        ]);
    }

    private static function section(Section $section): AuditSubject
    {
        return AuditSubject::of('section', (int) $section->id());
    }

    private static function year(int $schoolYearId): AuditSubject
    {
        return AuditSubject::of('school_year', $schoolYearId);
    }
}
