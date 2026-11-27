<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The result of the year proposed from the grades (EVA-R08.1 – R08.3), in
 * the terms of SPEC-010 (promoted, promoted_with_pending, repeats), or what
 * is missing to propose it (EVA-R08.5), and why when the grades alone do not
 * say it. The manager confirms it or records
 * another allowed result.
 */
final class OutcomeProposal
{
    private const int MOST_PENDING = 2;

    /**
     * Media General, with the final grades after their revision (RVP-R02.3):
     * no failed subject, promoted; 1 or 2, promoted with those subjects
     * pending; 3 or more, repeats. A failed final waits for its revision
     * (RVP-R02.4).
     * The pending subjects the student owes this year count too: one still
     * studied, or without its revision, waits (RVP-R05.4); one failed after
     * its revision repeats, saying which (RVP-R05.3); the approved ones do
     * not count, and the new failed ones keep the rule (Q4).
     *
     * @param  array<int, FinalAfterRevision>  $finals  Subject id => final grade after its revision.
     * @param  array<int, string>  $names  Subject id => name, to say what is missing.
     * @param  array<string, FinalAfterRevision>  $pendings  Name of the pending subject («Física de 2.º año») => its final grade after revision this year.
     * @param  bool  $thirdTermClosed  Whether the third term of the year is closed, so the pending subjects are decided.
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}
     */
    public static function forSecondary(array $finals, array $names, array $pendings = [], bool $thirdTermClosed = false): array
    {
        $missing = [];
        $failed = [];
        $failedPendings = [];

        foreach ($finals as $subjectId => $final) {
            $name = $names[$subjectId] ?? 'una materia';

            if ($final->state === FinalAfterRevision::NEEDS_REVISION) {
                $missing[] = "Falta la revisión de {$name}.";
            } elseif (! $final->isDecided()) {
                $missing[] = "Falta la definitiva de {$name}.";
            } elseif ($final->failed()) {
                $failed[] = $subjectId;
            }
        }

        foreach ($pendings as $name => $result) {
            $standing = PendingStanding::of($result, $thirdTermClosed);

            if ($standing === PendingStanding::FAILED) {
                $failedPendings[] = "Reprobó la pendiente {$name}.";
            } elseif ($standing === PendingStanding::STUDYING) {
                $missing[] = $thirdTermClosed && $result->state === FinalAfterRevision::NEEDS_REVISION
                    ? "Falta la revisión de la pendiente {$name}."
                    : "Falta la definitiva de la pendiente {$name}.";
            }
        }

        if ($missing !== []) {
            return self::none($missing);
        }

        if ($failedPendings !== []) {
            return self::proposal('repeats', reasons: $failedPendings);
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
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}
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
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}
     */
    public static function forInitial(): array
    {
        return self::proposal('promoted');
    }

    /**
     * @param  list<int>  $pendingSubjects
     * @param  list<string>  $reasons  Why, when the grades alone do not say it (RVP-R05.3).
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}
     */
    private static function proposal(string $outcome, array $pendingSubjects = [], array $reasons = []): array
    {
        return ['outcome' => $outcome, 'pendingSubjects' => $pendingSubjects, 'missing' => [], 'reasons' => $reasons];
    }

    /**
     * @param  list<string>  $missing
     * @return array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}
     */
    private static function none(array $missing): array
    {
        return ['outcome' => null, 'pendingSubjects' => [], 'missing' => $missing, 'reasons' => []];
    }
}
