<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application;

/**
 * The subjects of the MPPE catalog (CAT-R02), for other modules: SPEC-008
 * builds each grade's curriculum from them.
 */
interface Subjects
{
    /**
     * @return list<array{id: int, code: string, name: string}> By name.
     */
    public function ofLevel(string $level): array;

    /**
     * The level of each of these subjects; unknown ids are left out.
     *
     * @param  list<int>  $subjectIds
     * @return array<int, string> Subject id => level code.
     */
    public function levelsOf(array $subjectIds): array;
}
