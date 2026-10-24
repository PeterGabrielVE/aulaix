<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain\Subject;

use AulaX\Catalogs\Domain\CatalogCode;
use AulaX\Catalogs\Domain\EducationLevel;
use AulaX\Catalogs\Domain\InvalidCatalog;

/**
 * One área de formación of the MPPE curriculum.
 */
final readonly class SubjectEntry
{
    private function __construct(
        public CatalogCode $code,
        public string $name,
        public EducationLevel $level,
    ) {}

    public static function of(string $code, string $name, EducationLevel $level): self
    {
        if (trim($name) === '') {
            throw InvalidCatalog::missingName($code);
        }

        return new self(CatalogCode::fromString($code), $name, $level);
    }
}
