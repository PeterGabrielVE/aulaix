<?php

declare(strict_types=1);

namespace AulaX\Students\Application\UpdateStudent;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;

/**
 * A manager edits a student's data, identification and guardians with the
 * rules of the registration (EST-R01.5, R02, R03). Only what changed
 * reaches the audit log (EST-R10.1).
 */
final readonly class UpdateStudentHandler
{
    public function __construct(
        private StudentForm $form,
        private StudentRepository $students,
        private StudentPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws BusinessRuleViolations
     * @throws StudentNotFound
     */
    public function __invoke(UpdateStudentCommand $command): void
    {
        StudentAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($command) {
            $id = $command->studentId > 0 ? StudentId::fromInt($command->studentId) : throw StudentNotFound::create();
            $student = $this->students->byId($id) ?? throw StudentNotFound::create();
            $before = StudentAudit::details($student);

            $prepared = $this->form->prepare($command->student, $id);
            $student->update($prepared->personalData, $prepared->identification, $prepared->guardianships);

            $event = StudentAudit::updated($before, $student);

            if ($event->changes === []) {
                return;
            }

            $this->students->save($student);
            $this->audit->record($event);
        });
    }
}
