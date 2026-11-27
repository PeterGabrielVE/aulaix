<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Paginated document history result (A-08).
 */
final readonly class DocumentHistoryPage
{
    /**
     * @param  list<IssuedDocumentView>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $lastPage,
    ) {}
}
