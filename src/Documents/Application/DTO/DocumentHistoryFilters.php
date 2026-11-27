<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Filters for the institution-wide document history list (DOC-R05.5).
 */
final readonly class DocumentHistoryFilters
{
    public function __construct(
        public ?string $kind = null,
        public ?int $schoolYearId = null,
        public ?string $status = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
