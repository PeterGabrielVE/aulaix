<?php

use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\AlreadyEnrolled;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Enrollment\Domain\Placement;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R01.2, NF03: enrollments saved and read back whole, with the
 * unique rule of the database as a conflict.
 */
beforeEach(function () {
    tenant();
    $this->year = enrollmentYear();
    $this->sectionA = enrollmentSection($this->year, letter: 'A');
    $this->sectionB = enrollmentSection($this->year, letter: 'B');
    $this->student = enrollmentStudent();
    $this->repository = app(EnrollmentRepository::class);
});

function newEnrollment(int $student, int $year, int $section, ?AgeException $exception = null, ?string $overCapacity = null): Enrollment
{
    return Enrollment::enroll($student, $year, Placement::of($section, 'primaria_3', 'primaria'), new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-10-15'), $overCapacity, $exception, new DateTimeImmutable('2026-09-16 08:00:00'), 3);
}

it('saves an enrollment and reads it back whole', function () {
    $enrollment = newEnrollment($this->student, $this->year, $this->sectionA, AgeException::of('Repitió', approvedBy: 9, byDirective: true), 'Hermanos');
    $id = $this->repository->save($enrollment);
    $read = $this->repository->byId($id);

    expect($read?->id())->toEqual($id)
        ->and($read?->studentId())->toBe($this->student)
        ->and($read?->schoolYearId())->toBe($this->year)
        ->and($read?->placement())->toEqual(Placement::of($this->sectionA, 'primaria_3', 'primaria'))
        ->and($read?->period()->startsOn->format('Y-m-d'))->toBe('2026-09-16')
        ->and($read?->isCurrent())->toBeTrue()
        ->and($read?->overCapacityReason())->toBe('Hermanos')
        ->and($read?->ageException())->toEqual(AgeException::reconstitute('Repitió', 9))
        ->and($read?->registeredAt()->format('Y-m-d H:i'))->toBe('2026-09-16 08:00')
        ->and($read?->registeredBy())->toBe(3)
        ->and($this->repository->byId(EnrollmentId::fromInt($id->value + 100)))->toBeNull();
});

it('saves a change of section, an end and an annulment', function () {
    $first = newEnrollment($this->student, $this->year, $this->sectionA);
    $this->repository->save($first);
    $next = $first->moveTo(Placement::of($this->sectionB, 'primaria_3', 'primaria'), new DateTimeImmutable('2026-10-01'), 'Reorganización', new DateTimeImmutable('2026-10-15'), null, null, false, new DateTimeImmutable('2026-10-15 09:00'), 3);
    $this->repository->save($first);
    $nextId = $this->repository->save($next);

    $ended = $this->repository->byId($first->id());
    $current = $this->repository->byId($nextId);

    expect($ended?->period()->endsOn?->format('Y-m-d'))->toBe('2026-10-01')
        ->and($ended?->period()->endReason)->toBe(EndReason::SectionChange)
        ->and($ended?->endNote())->toBe('Reorganización')
        ->and($current?->previousId())->toBe($first->id()?->value);

    $current->annul(AnnulmentReason::Other, 'Sección equivocada', 3, new DateTimeImmutable('2026-10-15 10:00'), false, false);
    $this->repository->save($current);
    $annulled = $this->repository->byId($nextId);

    expect($annulled?->period()->endReason)->toBe(EndReason::Annulled)
        ->and($annulled?->annulment()?->reason)->toBe(AnnulmentReason::Other)
        ->and($annulled?->annulment()?->note)->toBe('Sección equivocada')
        ->and($annulled?->annulment()?->annulledBy)->toBe(3)
        ->and($annulled?->annulment()?->annulledAt->format('Y-m-d H:i'))->toBe('2026-10-15 10:00');
});

it('turns a second current enrollment in the year into a conflict', function () {
    $this->repository->save(newEnrollment($this->student, $this->year, $this->sectionA));

    expect(fn () => DB::transaction(fn () => $this->repository->save(newEnrollment($this->student, $this->year, $this->sectionB))))
        ->toThrow(AlreadyEnrolled::class);
});

it('finds the current enrollments, counts them by section and tells whether a section ever had one', function () {
    $other = enrollmentStudent('Álvarez', 'Luis');
    $first = newEnrollment($this->student, $this->year, $this->sectionA);
    $this->repository->save($first);
    $this->repository->save(newEnrollment($other, $this->year, $this->sectionA));
    $first->end(new DateTimeImmutable('2026-10-05'), EndReason::Withdrawal);
    $this->repository->save($first);
    $list = app(EnrollmentList::class);

    expect($this->repository->currentOf($other, $this->year)?->studentId())->toBe($other)
        ->and($this->repository->currentOf($this->student, $this->year))->toBeNull()
        ->and($this->repository->currentOfStudent($other))->toHaveCount(1)
        ->and($this->repository->currentCountIn($this->sectionA))->toBe(1)
        ->and($this->repository->hasAnyIn($this->sectionA))->toBeTrue()
        ->and($this->repository->hasAnyIn($this->sectionB))->toBeFalse()
        ->and($list->currentCountsOf($this->year))->toBe([$this->sectionA => 1])
        ->and($list->currentStudentsOf($this->year))->toBe([$other])
        ->and(array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), $list->currentIn($this->sectionA)))->toBe([$other])
        ->and($list->historyOf($this->student))->toHaveCount(1);
});
