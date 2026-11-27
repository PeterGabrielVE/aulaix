<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\SearchInstitutions;

use AulaX\Tenancy\Application\Administration\InstitutionPage;

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

    /**
     * Every institution, by name (SPEC-014 PLT-R04.1, R04.2).
     *
     * @param  string  $text  Matched against the name or the subdomain; empty for all.
     * @param  ?string  $status  active or inactive; null for both.
     */
    public function page(string $text, ?string $status, int $page, int $perPage): InstitutionPage;

    /**
     * One institution, active or not, as page() lists it (SPEC-014 PLT-R04.3).
     *
     * @return ?array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}
     */
    public function one(int $id): ?array;
}
