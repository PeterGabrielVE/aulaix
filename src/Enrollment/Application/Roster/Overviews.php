<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Port\SectionFact;
use Collator;

/**
 * Shared shaping of the read side: a section with its figures, and names in
 * Spanish order.
 */
final class Overviews
{
    public static function section(SectionFact $section, int $enrolled): SectionOverview
    {
        return new SectionOverview(
            $section->id,
            $section->grade,
            $section->gradeName,
            $section->letter,
            $section->capacity,
            $enrolled,
            $enrolled > $section->capacity,
        );
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  callable(T): string  $name
     * @return list<T>
     */
    public static function byName(array $items, callable $name): array
    {
        $collator = new Collator('es');
        usort($items, fn (object $a, object $b) => $collator->compare($name($a), $name($b)));

        return $items;
    }
}
