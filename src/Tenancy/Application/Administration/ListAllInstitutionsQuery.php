<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Administration;

use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;

/**
 * Every institution, active or not, for the platform (SPEC-014 PLT-R04.1,
 * R04.2): searched by name or subdomain and filtered by status. The caller
 * (Platform) checks who asks.
 */
final readonly class ListAllInstitutionsQuery
{
    private const int PER_PAGE = 50;

    public function __construct(private InstitutionDirectory $directory) {}

    /**
     * @param  ?string  $status  active or inactive; null for both.
     */
    public function __invoke(?string $search, ?string $status, int $page): InstitutionPage
    {
        return $this->directory->page(trim((string) $search), $status, max(1, $page), self::PER_PAGE);
    }
}
