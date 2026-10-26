<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Administration;

use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;

/**
 * One institution, active or not, for the platform (SPEC-014 PLT-R04.3).
 * The caller (Platform) checks who asks.
 */
final readonly class ShowInstitutionQuery
{
    public function __construct(private InstitutionDirectory $directory) {}

    /**
     * @return ?array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}
     */
    public function __invoke(int $id): ?array
    {
        return $this->directory->one($id);
    }
}
