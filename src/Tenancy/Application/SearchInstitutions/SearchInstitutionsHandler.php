<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\SearchInstitutions;

/**
 * Public search used by the central selector to help a visitor find their
 * institution's subdomain before logging in (TEN-R04.3, TEN-R04.4).
 */
final readonly class SearchInstitutionsHandler
{
    public function __construct(private InstitutionDirectory $directory) {}

    /**
     * @return list<InstitutionSummary>
     */
    public function __invoke(SearchInstitutionsQuery $query): array
    {
        return $this->directory->searchActive(trim($query->text), $query->limit);
    }
}
