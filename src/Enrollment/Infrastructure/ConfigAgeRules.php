<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure;

use AulaX\AcademicStructure\Application\Grades\GradesQuery;
use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Domain\AgeRule;
use InvalidArgumentException;

/**
 * The ages of config/enrollment.php (MAT-R03.6), checked when built: a
 * wrong entry fails at boot, not in an enrollment.
 */
final readonly class ConfigAgeRules implements AgeRules
{
    /** @var array<string, AgeRule> */
    private array $rules;

    /**
     * @param  array<string, array{int, int, int}>  $ages  grade => [regular, min, max]
     */
    public function __construct(private string $cutoff, array $ages)
    {
        [$month, $day] = array_map('intval', explode('-', $cutoff) + [1 => 0]);

        if (preg_match('/^\d{2}-\d{2}$/', $cutoff) !== 1 || ! checkdate($month, $day, 2024)) {
            throw new InvalidArgumentException("The age cutoff must be a month and day, MM-DD; got '{$cutoff}'.");
        }

        $grades = array_map(fn (GradeView $grade) => $grade->code, (new GradesQuery)());
        $rules = [];

        foreach ($ages as $grade => $age) {
            if (! in_array($grade, $grades, true) || ! is_array($age) || count($age) !== 3) {
                throw new InvalidArgumentException("The ages of '{$grade}' must be a known grade with [regular, min, max].");
            }

            $rules[$grade] = new AgeRule($grade, (int) $age[0], (int) $age[1], (int) $age[2]);
        }

        $this->rules = $rules;
    }

    public function cutoff(): string
    {
        return $this->cutoff;
    }

    public function of(string $grade): ?AgeRule
    {
        return $this->rules[$grade] ?? null;
    }
}
