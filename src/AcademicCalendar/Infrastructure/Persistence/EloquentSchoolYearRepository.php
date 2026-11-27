<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure\Persistence;

use AulaX\AcademicCalendar\Domain\DateRange;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicCalendar\Domain\Term;
use AulaX\AcademicCalendar\Domain\TermNumber;
use AulaX\AcademicCalendar\Domain\TermStatus;
use AulaX\Shared\Domain\Conflict;
use Illuminate\Database\QueryException;

final class EloquentSchoolYearRepository implements SchoolYearRepository
{
    /**
     * SQLSTATE of the rules ADR 0007 leaves to the database: a unique index
     * (one active, one planned, one open term) or the no-overlap exclusion.
     */
    private const RULE_VIOLATIONS = ['23505', '23P01'];

    public function byId(SchoolYearId $id): ?SchoolYear
    {
        $model = SchoolYearModel::query()->with('terms')->find($id->value);

        return $model === null ? null : self::toDomain($model);
    }

    public function othersOf(?SchoolYearId $except): array
    {
        return SchoolYearModel::query()
            ->with('terms')
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->value))
            ->orderBy('starts_on')
            ->get()
            ->map(fn (SchoolYearModel $model) => self::toDomain($model))
            ->values()
            ->all();
    }

    /**
     * @throws Conflict When a database rule breaks (ADR 0007): someone else
     *                  changed the calendar past every check.
     */
    public function save(SchoolYear $year): SchoolYearId
    {
        try {
            return $this->write($year);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), self::RULE_VIOLATIONS, true)) {
                throw new Conflict('Otra persona acaba de cambiar el calendario. Vuelve a cargar la página.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function write(SchoolYear $year): SchoolYearId
    {
        $model = $year->id() === null ? new SchoolYearModel : SchoolYearModel::query()->findOrFail($year->id()->value);

        $model->fill([
            'starts_on' => $year->dates()->start->format('Y-m-d'),
            'ends_on' => $year->dates()->end->format('Y-m-d'),
            'status' => $year->status()->value,
        ])->save();

        $id = SchoolYearId::fromInt((int) $model->id);
        $year->identify($id);

        // The open term last: one term open per year at most, also between
        // two statements (unique index terms_one_open).
        $terms = $year->terms();
        usort($terms, fn (Term $a, Term $b) => ($a->status === TermStatus::Open) <=> ($b->status === TermStatus::Open));

        foreach ($terms as $term) {
            TermModel::query()->updateOrCreate(
                ['school_year_id' => $id->value, 'number' => $term->number->value],
                [
                    'starts_on' => $term->dates->start->format('Y-m-d'),
                    'ends_on' => $term->dates->end->format('Y-m-d'),
                    'status' => $term->status->value,
                ],
            );
        }

        return $id;
    }

    public function delete(SchoolYearId $id): void
    {
        SchoolYearModel::query()->whereKey($id->value)->delete();
    }

    private static function toDomain(SchoolYearModel $model): SchoolYear
    {
        return SchoolYear::reconstitute(
            SchoolYearId::fromInt((int) $model->id),
            DateRange::fromStrings(self::day($model->starts_on), self::day($model->ends_on)),
            SchoolYearStatus::from($model->status),
            $model->terms->map(fn (TermModel $term) => new Term(
                TermNumber::of((int) $term->number),
                DateRange::fromStrings(self::day($term->starts_on), self::day($term->ends_on)),
                TermStatus::from($term->status),
            ))->values()->all(),
        );
    }

    private static function day(mixed $date): string
    {
        return substr((string) $date, 0, 10);
    }
}
