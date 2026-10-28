<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Domain\AgeCheck;
use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\AgeFit;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Application\Enrollable\EnrollableStudent;
use DateTimeImmutable;
use LogicException;

/**
 * The age of a student against the grade of a section (MAT-R03): a warning
 * outside the regular age, and outside the range the exception of a
 * directive, who is the person signed in (S-04).
 */
final readonly class AgeAdmission
{
    public function __construct(
        private AgeRules $rules,
        private CalendarFacts $calendar,
        private EnrollmentPermissions $permissions,
    ) {}

    /**
     * @return array{exception: ?AgeException, warning: ?string}
     *
     * @throws BusinessRuleViolation
     */
    public function admit(EnrollableStudent $student, SectionFact $section, ?string $justification, int $actorId): array
    {
        $start = $this->calendar->startOf($section->schoolYearId) ?? throw new LogicException('A section belongs to a school year.');
        $check = AgeCheck::of(new DateTimeImmutable($student->birthDate), $start, $this->rules->cutoff(), $this->rules->of($section->grade));

        $exception = $check->fit === AgeFit::OutOfRange && trim((string) $justification) !== ''
            ? AgeException::of($justification, $actorId, $this->permissions->canApproveExceptions())
            : null;

        return [
            'exception' => $check->admit($exception),
            'warning' => $check->fit === AgeFit::Warning
                ? "Tiene {$check->age} años; la edad regular de {$section->gradeName} es {$check->rule?->regular}."
                : null,
        ];
    }
}
