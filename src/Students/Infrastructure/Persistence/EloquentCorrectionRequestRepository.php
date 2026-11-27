<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Students\Domain\Correction\CorrectionRequest;
use AulaX\Students\Domain\Correction\CorrectionRequestId;
use AulaX\Students\Domain\Correction\CorrectionRequestRepository;
use AulaX\Students\Domain\Correction\CorrectionStatus;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Student\StudentId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentCorrectionRequestRepository implements CorrectionRequestRepository
{
    public function byId(CorrectionRequestId $id): ?CorrectionRequest
    {
        $row = DB::table('correction_requests')->where('id', $id->value)->first();

        return $row === null ? null : CorrectionRequest::reconstitute(
            CorrectionRequestId::fromInt((int) $row->id),
            GuardianId::fromInt((int) $row->guardian_id),
            $row->student_id === null ? null : StudentId::fromInt((int) $row->student_id),
            $row->text,
            CorrectionStatus::from($row->status),
            new DateTimeImmutable($row->submitted_at),
            $row->resolved_at === null ? null : new DateTimeImmutable($row->resolved_at),
        );
    }

    public function save(CorrectionRequest $request): CorrectionRequestId
    {
        $attributes = [
            'guardian_id' => $request->guardianId()->value,
            'student_id' => $request->studentId()?->value,
            'text' => $request->text(),
            'status' => $request->status()->value,
            'submitted_at' => $request->submittedAt()->format('Y-m-d H:i:sP'),
            'resolved_at' => $request->resolvedAt()?->format('Y-m-d H:i:sP'),
        ];

        if ($request->id() === null) {
            return CorrectionRequestId::fromInt((int) DB::table('correction_requests')->insertGetId($attributes));
        }

        DB::table('correction_requests')->where('id', $request->id()->value)->update($attributes);

        return $request->id();
    }
}
