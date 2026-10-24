<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\Conflict;

/**
 * The structure of the open institution's school years (Row Level Security
 * limits every read and write to it).
 */
interface SchoolYearStructureRepository
{
    /**
     * The year's structure, empty when it has none yet. Its status and the
     * institution's levels come from the calendar and the institution.
     *
     * @param  list<string>  $offeredLevels
     */
    public function load(int $schoolYearId, YearStatus $status, array $offeredLevels): SchoolYearStructure;

    /**
     * The school year a section of the institution belongs to.
     */
    public function yearOfSection(int $sectionId): ?int;

    /**
     * Saves the whole structure; new sections get their id, existing ones
     * keep it.
     *
     * @throws Conflict When a unique rule of the database breaks.
     */
    public function save(SchoolYearStructure $structure): void;
}
