<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\SearchInstitutions;

/**
 * Read side of institutions for the public selector (constitution A-08):
 * returns summaries straight from storage, without loading aggregates.
 */
interface InstitutionDirectory
{
    /**
     * Active institutions whose name contains $text (literally: % and _ are
     * not wildcards), ordered by name. An empty $text matches every one.
     *
     * @return list<InstitutionSummary>
     */
    public function searchActive(string $text, int $limit): array;
}
