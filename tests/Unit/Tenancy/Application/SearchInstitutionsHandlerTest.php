<?php

use AulaX\Tenancy\Application\Administration\InstitutionPage;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionSummary;
use AulaX\Tenancy\Application\SearchInstitutions\SearchInstitutionsHandler;
use AulaX\Tenancy\Application\SearchInstitutions\SearchInstitutionsQuery;

/**
 * A directory that records what it was asked and answers with fixed results.
 */
function directoryAnswering(InstitutionSummary ...$results): InstitutionDirectory
{
    return new class($results) implements InstitutionDirectory
    {
        /** @var list<array{0: string, 1: int}> */
        public array $searches = [];

        /** @param  list<InstitutionSummary>  $results */
        public function __construct(private readonly array $results) {}

        public function page(string $text, ?string $status, int $page, int $perPage): InstitutionPage
        {
            return new InstitutionPage([], 0, $page, $perPage);
        }

        public function one(int $id): ?array
        {
            return null;
        }

        public function searchActive(string $text, int $limit): array
        {
            $this->searches[] = [$text, $limit];

            return $this->results;
        }
    };
}

it('searches the active institutions with the trimmed text, ten at most', function () {
    $directory = directoryAnswering(new InstitutionSummary('Colegio Norte', 'norte'));

    $results = (new SearchInstitutionsHandler($directory))(new SearchInstitutionsQuery('  Norte '));

    expect($results)->toEqual([new InstitutionSummary('Colegio Norte', 'norte')])
        ->and($directory->searches)->toBe([['Norte', 10]]);
});
