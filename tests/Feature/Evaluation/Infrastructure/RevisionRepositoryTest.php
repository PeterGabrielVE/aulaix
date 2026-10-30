<?php

use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Revision;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentRevisionRepository;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R01.1, R01.7, R08.1: the revisions through the query
 * builder, in the open institution, and who already has the result of the
 * year recorded.
 */
beforeEach(function () {
    tenant();
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year, 'media_3');
    $this->subject = evaluationSubject();
    $this->ana = enrollmentStudent();
    $this->luis = enrollmentStudent('Álvarez', 'Luis');
    $this->repository = new EloquentRevisionRepository;
});

function revisionsOfSection(object $test): array
{
    return $test->repository->revisionsOf($test->year, $test->subject, PlanOwner::section($test->section), [$test->ana, $test->luis]);
}

it('saves, changes and clears the revisions of a section, and reads them by student', function () {
    $revisions = revisionsOfSection($this);

    expect($revisions[$this->ana]->mark())->toBeNull();

    $this->repository->saveRevision(Revision::of(null, $this->year, $this->subject, $this->ana, PlanOwner::section($this->section), GradeMark::fromInput('12,5')));
    $this->repository->saveRevision(Revision::of(null, $this->year, $this->subject, $this->luis, PlanOwner::section($this->section), GradeMark::notPresented()));
    $read = revisionsOfSection($this);

    expect($read[$this->ana]->mark()?->value())->toBe(1250)
        ->and($read[$this->luis]->mark()?->isNotPresented())->toBeTrue();

    $cleared = Revision::of($read[$this->luis]->id(), $this->year, $this->subject, $this->luis, PlanOwner::section($this->section), null);
    $this->repository->saveRevision($cleared);

    expect(revisionsOfSection($this)[$this->luis]->mark())->toBeNull()
        ->and(DB::table('grade_revisions')->count())->toBe(1);
});

it('keeps the revisions of a group of pending subjects apart from those of a section', function () {
    $group = DB::table('pending_groups')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->subject, 'origin_grade' => 'media_2']);
    $this->repository->saveRevision(Revision::of(null, $this->year, $this->subject, $this->ana, PlanOwner::pendingGroup($group), GradeMark::fromInput('15')));

    expect(revisionsOfSection($this)[$this->ana]->mark())->toBeNull()
        ->and($this->repository->revisionsOf($this->year, $this->subject, PlanOwner::pendingGroup($group), [$this->ana])[$this->ana]->mark()?->value())->toBe(1500);
});

it('tells who already has the result of the year recorded', function () {
    enrollmentRow($this->ana, $this->year, $this->section, ['grade' => 'media_3', 'outcome' => 'promoted', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1]);
    enrollmentRow($this->luis, $this->year, $this->section, ['grade' => 'media_3']);

    expect(app(EnrollmentFacts::class)->resultsRecorded($this->year, [$this->ana, $this->luis]))->toBe([$this->ana]);
});
