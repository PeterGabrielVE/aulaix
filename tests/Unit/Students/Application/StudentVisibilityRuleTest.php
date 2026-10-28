<?php

use AulaX\Students\Application\FindGuardian\GuardianView;
use AulaX\Students\Application\ListStudents\ListStudentsQuery;
use AulaX\Students\Application\ListStudents\StudentPage;
use AulaX\Students\Application\Port\StudentVisibilityRule;
use AulaX\Students\Application\ShowStudent\ShowStudentQuery;
use AulaX\Students\Application\ShowStudent\StudentView;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Application\StudentVisibility;
use AulaX\Students\Domain\Identity\GuardianDocument;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-010 MAT-R08.2, R08.4 (ADR 0012): another module may narrow which
 * students the person signed in sees; with no rule, everyone is seen.
 */
function visibilityRule(?array $ids): StudentVisibilityRule
{
    return new class($ids) implements StudentVisibilityRule
    {
        public function __construct(private readonly ?array $ids) {}

        public function visibleStudents(): ?array
        {
            return $this->ids;
        }
    };
}

beforeEach(function () {
    $this->directory = new class implements StudentDirectory
    {
        public ?array $askedFor = null;

        public array $recordsRead = [];

        public function page(string $search, bool $includeWithdrawn, int $page, int $perPage, ?array $onlyIds = null): StudentPage
        {
            $this->askedFor = $onlyIds;

            return new StudentPage([], 0, $page, $perPage);
        }

        public function student(int $id, bool $includeHealth): ?StudentView
        {
            $this->recordsRead[] = $id;

            return null;
        }

        public function guardianByDocument(GuardianDocument $document): ?GuardianView
        {
            return null;
        }

        public function representedBy(int $guardianId): array
        {
            return [];
        }

        public function soleLegalGuardianOf(int $guardianId): array
        {
            return [];
        }

        public function enrollable(?array $ids): array
        {
            return [];
        }
    };
});

it('with no rule, everyone is seen', function () {
    expect((new StudentVisibility([]))->visibleStudents())->toBeNull()
        ->and((new StudentVisibility([visibilityRule(null)]))->visibleStudents())->toBeNull();
});

it('the students seen are those every rule lets through', function () {
    expect((new StudentVisibility([visibilityRule([3, 1, 2]), visibilityRule(null), visibilityRule([2, 3, 7])]))->visibleStudents())->toBe([2, 3]);
});

it('the list only asks for the students seen', function () {
    (new ListStudentsQuery($this->directory, new SwitchableStudentPermissions, new StudentVisibility([visibilityRule([5])])))('', false, 1);

    expect($this->directory->askedFor)->toBe([5]);

    (new ListStudentsQuery($this->directory, new SwitchableStudentPermissions))('', false, 1);

    expect($this->directory->askedFor)->toBeNull();
});

it('the record of a student not seen does not exist', function () {
    $query = new ShowStudentQuery($this->directory, new SwitchableStudentPermissions, new StudentVisibility([visibilityRule([5])]));

    expect(fn () => $query(6, includeHealth: false))->toThrow(StudentNotFound::class)
        ->and($this->directory->recordsRead)->toBe([])
        ->and(fn () => $query(5, includeHealth: false))->toThrow(StudentNotFound::class)
        ->and($this->directory->recordsRead)->toBe([5]);
});
