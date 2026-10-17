<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

/**
 * The cases support has to resolve (IDG-R11.1).
 */
final readonly class ListConsolidationCasesHandler
{
    public function __construct(private ConsolidationCases $cases) {}

    /**
     * @return list<ConsolidationCase>
     */
    public function __invoke(): array
    {
        return $this->cases->underReview();
    }
}
