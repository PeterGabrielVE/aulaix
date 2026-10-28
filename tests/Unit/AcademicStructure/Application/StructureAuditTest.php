<?php

use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\AcademicStructure\Application\RemovalGuards;
use AulaX\AcademicStructure\Application\StructureAudit;
use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\Section;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\Audit\Application\FieldChange;

/**
 * SPEC-008 EAC-R11.1: what the audit log keeps of the structure; and the
 * extension point SPEC-010 and 011 will fill (EAC-R02.6, R07.3).
 */
function structureChanges(array $changes): array
{
    return array_combine(
        array_map(fn (FieldChange $change) => $change->field, $changes),
        array_map(fn (FieldChange $change) => [$change->from, $change->to], $changes),
    );
}

it('records a new section with its grade, letter and capacity', function () {
    $section = new Section(12, Grade::Media1, SectionLetter::fromString('B'), Capacity::of(42, 'Alta demanda', Grade::Media1));

    $event = StructureAudit::sectionCreated(7, $section);

    expect($event->type)->toBe('structure.section.created')
        ->and($event->subject->id)->toBe('12')
        ->and(structureChanges($event->changes))->toBe([
            'school_year_id' => [null, 7],
            'grade' => [null, 'media_1'],
            'letter' => [null, 'B'],
            'capacity' => [null, 42],
            'capacity_reason' => [null, 'Alta demanda'],
        ]);
});

it('records the subjects a curriculum gained and lost', function () {
    $event = StructureAudit::curriculumChanged(7, Grade::Primaria1, [1, 2], [2, 3]);

    expect($event->type)->toBe('structure.curriculum.changed')
        ->and(structureChanges($event->changes))->toBe(['grade' => [null, 'primaria_1'], 'subjects' => [[1, 2], [2, 3]]]);
});

it('treats nothing as in use while no other module says so', function () {
    $guards = new RemovalGuards([]);

    expect($guards->enrolledIn(5))->toBe(0)
        ->and($guards->sectionInUse(5))->toBeFalse()
        ->and($guards->subjectsInUse(7, Grade::Primaria1, [1, 2]))->toBe([]);
});

it('asks every registered guard', function () {
    $enrollment = new class implements RemovalGuard
    {
        public function enrolledIn(int $sectionId): int
        {
            return 18;
        }

        public function hasEnrollments(int $sectionId): bool
        {
            return true;
        }

        public function subjectsInUse(int $schoolYearId, string $grade, array $subjectIds): array
        {
            return [];
        }
    };
    $grades = new class implements RemovalGuard
    {
        public function enrolledIn(int $sectionId): int
        {
            return 0;
        }

        public function hasEnrollments(int $sectionId): bool
        {
            return false;
        }

        public function subjectsInUse(int $schoolYearId, string $grade, array $subjectIds): array
        {
            return [2];
        }
    };

    $guards = new RemovalGuards([$enrollment, $grades]);

    expect($guards->enrolledIn(5))->toBe(18)
        ->and($guards->sectionInUse(5))->toBeTrue()
        ->and($guards->subjectsInUse(7, Grade::Primaria1, [1, 2]))->toBe([2]);
});
