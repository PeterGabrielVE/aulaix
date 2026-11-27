<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\Correction\CorrectionView;
use AulaX\Students\Application\Correction\GuardianCorrectionsQuery;
use AulaX\Students\Application\Correction\ListCorrectionsQuery;
use AulaX\Students\Application\GuardianPortal\GuardianStudentsQuery;
use AulaX\Students\Application\ListStudents\ListStudentsQuery;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\Transfer\ListTransfersQuery;
use AulaX\Students\Application\Transfer\TransferView;
use AulaX\Workspace\Application\Port\StudentSource;

/**
 * Through Students' public queries (A-06), the same the students, transfer,
 * correction and guardian screens use, with their permission checks (S-04).
 */
final readonly class StudentsSource implements StudentSource
{
    private const string PENDING = 'pending';

    public function __construct(
        private ListStudentsQuery $students,
        private ListTransfersQuery $transfers,
        private ListCorrectionsQuery $corrections,
        private GuardianStudentsQuery $guardianStudents,
        private GuardianCorrectionsQuery $guardianCorrections,
    ) {}

    public function activeStudents(): int
    {
        return ($this->students)('', includeWithdrawn: false, page: 1)->total;
    }

    public function pendingRequests(): array
    {
        return [
            'transfers' => count(array_filter(($this->transfers)()->incoming, fn (TransferView $transfer) => $transfer->status === self::PENDING)),
            'corrections' => self::pending(($this->corrections)()),
        ];
    }

    public function representedBy(int $accountId): array
    {
        try {
            return [
                'students' => array_map(fn (StudentListItem $student) => ['id' => $student->id, 'name' => $student->fullName], ($this->guardianStudents)($accountId)),
                'pendingCorrections' => self::pending(($this->guardianCorrections)($accountId)),
            ];
        } catch (AuthorizationDenied) {
            // NAV-R08.3: a Representante not linked to any student here yet.
            return ['students' => [], 'pendingCorrections' => 0];
        }
    }

    /**
     * @param  list<CorrectionView>  $corrections
     */
    private static function pending(array $corrections): int
    {
        return count(array_filter($corrections, fn (CorrectionView $correction) => $correction->status === self::PENDING));
    }
}
