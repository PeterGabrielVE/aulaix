<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Application;

use AulaX\Catalogs\Domain\EducationLevel;

/**
 * The education levels of the catalog, for other modules (A-06: they may
 * not read this module's domain). Used by SPEC-006 for the levels an
 * institution offers.
 */
final class EducationLevels
{
    private const NAMES = [
        'inicial' => 'Educación Inicial',
        'primaria' => 'Educación Primaria',
        'media_general' => 'Educación Media General',
    ];

    /**
     * @return list<array{code: string, name: string}>
     */
    public function all(): array
    {
        return array_map(
            fn (EducationLevel $level) => ['code' => $level->value, 'name' => self::NAMES[$level->value] ?? $level->value],
            EducationLevel::cases(),
        );
    }

    public function exists(string $code): bool
    {
        return EducationLevel::tryFrom($code) !== null;
    }
}
