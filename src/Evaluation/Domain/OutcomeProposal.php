<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The result of the year proposed from the grades (EVA-R08.1 – R08.3), in
 * the terms of SPEC-010 (promoted, promoted_with_pending, repeats), or what
 * is missing to propose it (EVA-R08.5). The manager confirms it or records
 * another allowed result.
 */
final class OutcomeProposal
{
    private const int MOST_PENDING = 2;

    /**
     * Media General: no failed subject, promoted; 1 or 2, promoted with those
     * subjects pending; 3 or more, repeats.
     *
     * @param  array<int, FinalScore>  $finals  Subject id => final grade.
     * @param  array<int, string>  $names  Subject id => name, to say what is missing.
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}
     */
    public static function forSecondary(array $finals, array $names): array
    {
        $missing = [];
        $failed = [];

        foreach ($finals as $subjectId => $final) {
            if (! $final->isComplete()) {
                $missing[] = 'Falta la definitiva de '.($names[$subjectId] ?? 'una materia').'.';
            } elseif ($final->failed()) {
                $failed[] = $subjectId;
            }
        }

        if ($missing !== []) {
            return self::none($missing);
        }

        return match (true) {
            $failed === [] => self::proposal('promoted'),
            count($failed) <= self::MOST_PENDING => self::proposal('promoted_with_pending', $failed),
            default => self::proposal('repeats'),
        };
    }

    /**
     * Primaria: E repeats; A – D, promoted.
     *
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}
     */
    public static function forPrimary(?Literal $final): array
    {
        return match ($final) {
            null => self::none(['Falta el literal final.']),
            Literal::E => self::proposal('repeats'),
            default => self::proposal('promoted'),
        };
    }

    /**
     * Inicial: always promoted.
     *
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}
     */
    public static function forInitial(): array
    {
        return self::proposal('promoted');
    }

    /**
     * @param  list<int>  $pendingSubjects
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}
     */
    private static function proposal(string $outcome, array $pendingSubjects = []): array
    {
        return ['outcome' => $outcome, 'pendingSubjects' => $pendingSubjects, 'missing' => []];
    }

    /**
     * @param  list<string>  $missing
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}
     */
    private static function none(array $missing): array
    {
        return ['outcome' => null, 'pendingSubjects' => [], 'missing' => $missing];
    }
}
