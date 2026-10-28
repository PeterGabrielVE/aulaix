<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The literals (three terms and the final one) and the reports (three
 * terms) of a student in a school year (EVA-R04, R05). A literal is A – E
 * with an optional description; the final one, only once the third term is
 * closed; a report, 1 to 5000 characters. The term closed by exception
 * leaves the missing ones incomplete (EVA-R12.3).
 */
final class StudentMarks
{
    private const int MAX_TEXT = 5000;

    private const string INCOMPLETE = 'incompleto';

    private const string WRITTEN_DESCRIPTION = 'escrita';

    private const string WRITTEN_REPORT = 'escrito';

    /**
     * @param  array<string, Mark>  $marks  "period:kind" => mark.
     */
    private function __construct(
        private readonly int $studentId,
        private readonly int $schoolYearId,
        private array $marks,
    ) {}

    /**
     * @param  list<Mark>  $marks
     */
    public static function of(int $studentId, int $schoolYearId, array $marks): self
    {
        $keyed = [];

        foreach ($marks as $mark) {
            $keyed["{$mark->period}:{$mark->kind->value}"] = $mark;
        }

        return new self($studentId, $schoolYearId, $keyed);
    }

    /**
     * @return list<MarkChange>
     *
     * @throws MarkNotValid
     */
    public function recordLiteral(int $term, Literal $literal, ?string $description): array
    {
        return $this->literal((string) $term, $literal, $description);
    }

    /**
     * @return list<MarkChange>
     *
     * @throws MarkNotValid
     */
    public function recordFinalLiteral(Literal $literal, ?string $description, bool $thirdTermClosed): array
    {
        if (! $thirdTermClosed) {
            throw MarkNotValid::finalTooEarly();
        }

        return $this->literal('final', $literal, $description);
    }

    /**
     * @return list<MarkChange>
     *
     * @throws MarkNotValid
     */
    public function recordReport(int $term, string $text): array
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > self::MAX_TEXT) {
            throw MarkNotValid::reportLength();
        }

        $period = (string) $term;
        $before = $this->markOf($period, MarkKind::Report);

        if ($before !== null && ! $before->incomplete && $before->description === $text) {
            return [];
        }

        $this->put(new Mark($period, MarkKind::Report, null, $text, false));

        return [new MarkChange("report_{$period}", $before === null ? null : ($before->incomplete ? self::INCOMPLETE : self::WRITTEN_REPORT), self::WRITTEN_REPORT)];
    }

    /**
     * Marks as incomplete a literal or report that is missing.
     *
     * @return bool Whether it was missing.
     */
    public function fillIncomplete(string $period, MarkKind $kind): bool
    {
        if (! $this->isMissing($period, $kind)) {
            return false;
        }

        $this->put(Mark::incomplete($period, $kind));

        return true;
    }

    public function isMissing(string $period, MarkKind $kind): bool
    {
        return $this->markOf($period, $kind) === null;
    }

    public function markOf(string $period, MarkKind $kind): ?Mark
    {
        return $this->marks["{$period}:{$kind->value}"] ?? null;
    }

    /**
     * @return list<Mark>
     */
    public function marks(): array
    {
        return array_values($this->marks);
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function schoolYearId(): int
    {
        return $this->schoolYearId;
    }

    /**
     * @return list<MarkChange>
     *
     * @throws MarkNotValid
     */
    private function literal(string $period, Literal $literal, ?string $description): array
    {
        $description = trim((string) $description);
        $description = $description === '' ? null : $description;

        if ($description !== null && mb_strlen($description) > self::MAX_TEXT) {
            throw MarkNotValid::descriptionTooLong();
        }

        $before = $this->markOf($period, MarkKind::Literal);
        $changes = [];
        $beforeLiteral = $before === null ? null : ($before->incomplete ? self::INCOMPLETE : $before->literal?->value);

        if ($beforeLiteral !== $literal->value) {
            $changes[] = new MarkChange("literal_{$period}", $beforeLiteral, $literal->value);
        }

        if (($before?->description) !== $description) {
            $changes[] = new MarkChange(
                "description_{$period}",
                $before?->description === null ? null : self::WRITTEN_DESCRIPTION,
                $description === null ? null : self::WRITTEN_DESCRIPTION,
            );
        }

        $this->put(new Mark($period, MarkKind::Literal, $literal, $description, false));

        return $changes;
    }

    private function put(Mark $mark): void
    {
        $this->marks["{$mark->period}:{$mark->kind->value}"] = $mark;
    }
}
