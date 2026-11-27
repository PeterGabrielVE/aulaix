<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\FieldChange;
use AulaX\Students\Domain\Transfer\TransferRequest;

/**
 * The audit log entries of a transfer, in each institution's own log
 * (EST-R10.2): the request in the destination, the answer in the origin.
 */
final class TransferAudit
{
    public static function requested(TransferRequest $request): AuditEvent
    {
        return AuditEvent::of('students.transfer.requested', self::subject($request), [
            FieldChange::of('identification', null, $request->identification()->value()),
        ]);
    }

    public static function approved(TransferRequest $request): AuditEvent
    {
        return AuditEvent::of('students.transfer.approved', self::subject($request), [
            FieldChange::of('status', 'pending', 'approved'),
            FieldChange::of('identification', null, $request->identification()->value()),
            FieldChange::of('destination', null, $request->destinationName()),
        ]);
    }

    public static function rejected(TransferRequest $request): AuditEvent
    {
        return AuditEvent::of('students.transfer.rejected', self::subject($request), [
            FieldChange::of('status', 'pending', 'rejected'),
            FieldChange::of('identification', null, $request->identification()->value()),
            FieldChange::of('destination', null, $request->destinationName()),
            FieldChange::of('rejection_reason', null, $request->rejectionReason()),
        ]);
    }

    private static function subject(TransferRequest $request): AuditSubject
    {
        return AuditSubject::of('transfer_request', (int) $request->id()?->value);
    }
}
