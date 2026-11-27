<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

use AulaX\Documents\Application\DTO\DocumentHistoryFilters;
use AulaX\Documents\Application\DTO\DocumentHistoryPage;
use AulaX\Documents\Application\DTO\IssuedDocumentView;
use DateTimeImmutable;

/**
 * CQRS read port for internal document history tables (A-08, DOC-R05.5).
 */
interface DocumentHistoryDirectory
{
    /**
     * @param  ?list<string>  $allowedGrades  Null means full institution scope; list restricts Coordinator.
     */
    public function paginate(DocumentHistoryFilters $filters, ?array $allowedGrades, DateTimeImmutable $now): DocumentHistoryPage;

    /**
     * @param  ?list<string>  $allowedGrades  Null means full institution scope; list restricts Coordinator.
     * @return list<IssuedDocumentView>
     */
    public function forStudent(int $studentId, ?array $allowedGrades, DateTimeImmutable $now): array;
}
