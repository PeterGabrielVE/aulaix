<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain\Subject;

use AulaX\Catalogs\Domain\EducationLevel;
use AulaX\Catalogs\Domain\InvalidCatalog;

/**
 * The reference subjects catalog, validated as a whole. It is authoritative:
 * synchronizing it removes the subjects it no longer lists (CAT-R03.3).
 */
final readonly class SubjectCatalog
{
    /**
     * @param  list<SubjectEntry>  $entries
     */
    private function __construct(private array $entries) {}

    public static function of(SubjectEntry ...$entries): self
    {
        $seen = [];

        foreach ($entries as $entry) {
            if (isset($seen[$entry->code->value])) {
                throw InvalidCatalog::duplicateCode($entry->code->value);
            }

            $seen[$entry->code->value] = true;
        }

        foreach (EducationLevel::cases() as $level) {
            $hasSubjects = array_filter($entries, fn (SubjectEntry $entry) => $entry->level === $level) !== [];

            if (! $hasSubjects) {
                throw InvalidCatalog::levelWithoutSubjects($level);
            }
        }

        return new self(array_values($entries));
    }

    /**
     * @return list<SubjectEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(fn (SubjectEntry $entry) => $entry->code->value, $this->entries);
    }
}
