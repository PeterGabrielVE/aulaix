<?php

declare(strict_types=1);

namespace AulaX\Students\Application\StudentForDocuments;

use AulaX\Students\Application\StudentDirectory;

/**
 * Cross-module query for SPEC-012 Documents (A-06): returns a student's
 * identifying fields and latest withdrawal without requiring `ver-estudiantes`
 * (the Documents module enforces `emitir-documentos` and Coordinator scope).
 */
final readonly class StudentForDocumentsQuery
{
    public function __construct(private StudentDirectory $directory) {}

    public function __invoke(int $studentId): ?StudentDocumentProfile
    {
        $student = $this->directory->student($studentId, includeHealth: false);

        if ($student === null) {
            return null;
        }

        $latestWithdrawal = null;

        foreach ($student->withdrawals as $withdrawal) {
            if ($withdrawal->reinstatedOn === null) {
                $latestWithdrawal = $withdrawal;
            }
        }

        if ($latestWithdrawal === null && $student->withdrawals !== []) {
            $latestWithdrawal = $student->withdrawals[array_key_last($student->withdrawals)];
        }

        $reason = $latestWithdrawal === null
            ? null
            : trim(implode(' — ', array_filter([$latestWithdrawal->reason, $latestWithdrawal->note])));

        return new StudentDocumentProfile(
            id: $student->id,
            givenNames: $student->givenNames,
            familyNames: $student->familyNames,
            fullName: trim("{$student->givenNames} {$student->familyNames}"),
            identification: $student->identification,
            status: $student->status,
            latestWithdrawalDate: $latestWithdrawal?->withdrawnOn,
            latestWithdrawalReason: $reason !== '' ? $reason : null,
        );
    }
}
