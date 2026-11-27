<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Transfer\TransferRequest;
use AulaX\Students\Domain\Transfer\TransferRequestId;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Students\Domain\Transfer\TransferStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Row Level Security (ADR 0008) decides which requests the open
 * institution reads; a new request takes the open institution as its
 * destination from the column default.
 */
final class EloquentTransferRequestRepository implements TransferRequestRepository
{
    public function byId(TransferRequestId $id): ?TransferRequest
    {
        $row = DB::table('transfer_requests')->where('id', $id->value)->first();

        return $row === null ? null : self::toDomain($row);
    }

    public function pendingOf(InstitutionId $destination, StudentIdentification $identification): ?TransferRequest
    {
        $row = DB::table('transfer_requests')
            ->where('institution_id', $destination->value)
            ->where('identification', $identification->value())
            ->where('status', TransferStatus::Pending->value)
            ->first();

        return $row === null ? null : self::toDomain($row);
    }

    public function save(TransferRequest $request): TransferRequestId
    {
        $attributes = [
            'destination_name' => $request->destinationName(),
            'identification' => $request->identification()->value(),
            'status' => $request->storedStatus()->value,
            'origin_institution_id' => $request->originInstitutionId()?->value,
            'origin_name' => $request->originName(),
            'rejection_reason' => $request->rejectionReason(),
            'requested_at' => $request->requestedAt()->format('Y-m-d H:i:sP'),
            'answered_at' => $request->answeredAt()?->format('Y-m-d H:i:sP'),
        ];

        if ($request->id() === null) {
            return TransferRequestId::fromInt((int) DB::table('transfer_requests')->insertGetId($attributes));
        }

        DB::table('transfer_requests')->where('id', $request->id()->value)->update($attributes);

        return $request->id();
    }

    private static function toDomain(object $row): TransferRequest
    {
        return TransferRequest::reconstitute(
            TransferRequestId::fromInt((int) $row->id),
            InstitutionId::fromInt((int) $row->institution_id),
            StudentIdentification::fromInput(strlen($row->identification) === 12 ? StudentIdentification::SCHOOL_ID : StudentIdentification::NATIONAL_ID, $row->identification),
            $row->destination_name,
            TransferStatus::from($row->status),
            $row->origin_institution_id === null ? null : InstitutionId::fromInt((int) $row->origin_institution_id),
            $row->origin_name,
            $row->rejection_reason,
            new DateTimeImmutable($row->requested_at),
            $row->answered_at === null ? null : new DateTimeImmutable($row->answered_at),
        );
    }
}
