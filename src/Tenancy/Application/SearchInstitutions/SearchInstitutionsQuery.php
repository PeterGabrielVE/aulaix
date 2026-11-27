<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\SearchInstitutions;

final readonly class SearchInstitutionsQuery
{
    public function __construct(
        public string $text,
        public int $limit = 10,
    ) {}
}
