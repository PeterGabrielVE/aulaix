<?php

declare(strict_types=1);

namespace AulaX\Students\Application\RegisterStudent;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;

/**
 * A manager registers a student of the open institution with its guardians
 * (EST-R01, R02, R03, R04.1), recorded in the audit log (EST-R10.1).
 */
final readonly class RegisterStudentHandler
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
     */
    public function __invoke(RegisterStudentCommand $command): StudentId
    {
        StudentAccess::ensureCanManage($this->permissions);

        return $this->transactions->run(function () use ($command) {
            $health = null;
            $broken = [];

            try {
                $health = HealthRecord::fromInput($command->allergies, $command->medicalConditions, $command->disability);
            } catch (BusinessRuleViolations $violations) {
                $broken = $violations->violations();
            }

            $prepared = $this->form->prepare($command->student, null, $broken);
            $student = Student::register($prepared->personalData, $prepared->identification, $prepared->guardianships, $health);

            $id = $this->students->save($student);
            $student->identify($id);
            $this->audit->record(StudentAudit::registered($student));

            return $id;
        });
    }
}
