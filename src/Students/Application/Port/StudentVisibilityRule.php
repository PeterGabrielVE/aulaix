<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Port;

/**
 * An extension point for the modules that build on students (ADR 0012):
 * SPEC-010 narrows Docente and Coordinador to the students of their
 * sections or grades (MAT-R08). Until a module registers one, whoever may
 * see students sees them all.
 */
interface StudentVisibilityRule
{
    /**
     * The container tag a module puts its rule under.
     */
    public const string TAG = 'students.visibility_rules';

    /**
     * The students the person signed in may see, or null for all of them.
     *
     * @return ?list<int>
     */
    public function visibleStudents(): ?array;
}
