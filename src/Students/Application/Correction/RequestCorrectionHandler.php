<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Correction;

use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\GuardianPortal\LinkedGuardian;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Domain\Correction\CorrectionRequest;
use AulaX\Students\Domain\Correction\CorrectionRequestRepository;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Student\StudentId;

/**
 * A guardian asks the institution to correct a datum of its own record or of
 * one of its students (EST-R08.4).
 */
final readonly class RequestCorrectionHandler
{
    public function __construct(
        private GuardianRepository $guardians,
        private StudentDirectory $directory,
        private CorrectionRequestRepository $corrections,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AuthorizationDenied When the account represents no student here.
     * @throws StudentNotFound When the student is not one it represents.
     * @throws BusinessRuleViolation When the text is empty or too long.
     */
    public function __invoke(int $accountId, ?int $studentId, string $text): void
    {
        $guardianId = LinkedGuardian::of($this->guardians, $accountId);

        if ($studentId !== null && ! in_array($studentId, array_map(fn (StudentListItem $student) => $student->id, $this->directory->representedBy($guardianId)), true)) {
            throw StudentNotFound::create();
        }

        $request = CorrectionRequest::submit(
            GuardianId::fromInt($guardianId),
            $studentId === null ? null : StudentId::fromInt($studentId),
            $text,
            $this->clock->now(),
        );

        $this->transactions->run(fn () => $this->corrections->save($request));
    }
}
