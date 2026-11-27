<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\Transfer\TransferBoard;
use AulaX\Students\Application\Transfer\TransferList;
use AulaX\Students\Application\Transfer\TransferView;
use AulaX\Students\Domain\Transfer\TransferRequest;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Row Level Security (ADR 0008) already limits the rows to the open
 * institution's own requests, the ones it answered and the pending ones for
 * its active students. Joining students (under their own RLS) names only the
 * open institution's students.
 */
final class EloquentTransferList implements TransferList
{
    private const int LIMIT = 100;

    public function boardOf(InstitutionId $institution, DateTimeImmutable $now): TransferBoard
    {
        $incoming = $this->rows(fn (Builder $query) => $query->where('transfer_requests.institution_id', '!=', $institution->value), withStudent: true);
        $outgoing = $this->rows(fn (Builder $query) => $query->where('transfer_requests.institution_id', $institution->value), withStudent: false);

        return new TransferBoard(
            array_map(fn (object $row) => self::view($row, $now), $incoming),
            array_map(fn (object $row) => self::view($row, $now), $outgoing),
        );
    }

    /**
     * @param  callable(Builder): Builder  $side
     * @return list<object>
     */
    private function rows(callable $side, bool $withStudent): array
    {
        $query = DB::table('transfer_requests')->select('transfer_requests.*');

        if ($withStudent) {
            $query->leftJoin('students', 'students.identification', '=', 'transfer_requests.identification')
                ->addSelect(DB::raw("students.family_names || ', ' || students.given_names as student_name"));
        }

        return $side($query)
            ->orderByDesc('transfer_requests.requested_at')
            ->orderByDesc('transfer_requests.id')
            ->limit(self::LIMIT)
            ->get()
            ->all();
    }

    private static function view(object $row, DateTimeImmutable $now): TransferView
    {
        $requestedAt = new DateTimeImmutable($row->requested_at);
        $lastDay = $requestedAt->setTime(0, 0)->modify('+'.TransferRequest::DAYS_TO_ANSWER.' days');
        $expired = $row->status === 'expired' || ($row->status === 'pending' && $now->setTime(0, 0) > $lastDay);

        return new TransferView(
            (int) $row->id,
            $row->identification,
            $row->student_name ?? null,
            $row->destination_name,
            $row->origin_name,
            $expired ? 'expired' : $row->status,
            $requestedAt->format('Y-m-d'),
            $lastDay->format('Y-m-d'),
            $row->rejection_reason,
        );
    }
}
