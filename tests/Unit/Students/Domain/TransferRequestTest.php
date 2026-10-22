<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Transfer\TransferRequest;
use AulaX\Students\Domain\Transfer\TransferStatus;

/**
 * SPEC-009 EST-R07.1, R07.5 – R07.7: a destination institution asks for a
 * student; the origin approves or rejects it within 15 calendar days.
 */
function requestedOn(string $moment = '2026-10-01 09:00'): TransferRequest
{
    return TransferRequest::request(InstitutionId::fromInt(9), StudentIdentification::fromInput('school_id', 'V11915432100'), 'Colegio Sur', new DateTimeImmutable($moment));
}

it('is requested for an identification, pending and without an origin', function () {
    $transfer = requestedOn();

    expect($transfer->status(new DateTimeImmutable('2026-10-01 10:00')))->toBe(TransferStatus::Pending)
        ->and($transfer->identification()->value())->toBe('V11915432100')
        ->and($transfer->destinationName())->toBe('Colegio Sur')
        ->and($transfer->destinationId()->value)->toBe(9)
        ->and($transfer->originInstitutionId())->toBeNull();
});

it('expires after 15 calendar days without an answer', function (string $now, TransferStatus $status) {
    expect(requestedOn()->status(new DateTimeImmutable($now)))->toBe($status);
})->with([
    'day 15, late in the day' => ['2026-10-16 23:59', TransferStatus::Pending],
    'day 16' => ['2026-10-17 00:00', TransferStatus::Expired],
]);

it('is approved by the origin, which it then names', function () {
    $transfer = requestedOn();

    $transfer->approve(InstitutionId::fromInt(4), 'Colegio Norte', new DateTimeImmutable('2026-10-10'));

    expect($transfer->status(new DateTimeImmutable('2026-12-01')))->toBe(TransferStatus::Approved)
        ->and($transfer->originInstitutionId()->value)->toBe(4)
        ->and($transfer->originName())->toBe('Colegio Norte')
        ->and($transfer->answeredAt()->format('Y-m-d'))->toBe('2026-10-10');
});

it('is rejected by the origin with a reason', function () {
    $transfer = requestedOn();

    $transfer->reject(InstitutionId::fromInt(4), 'Colegio Norte', ' Tiene deudas de documentos ', new DateTimeImmutable('2026-10-10'));

    expect($transfer->status(new DateTimeImmutable('2026-10-10')))->toBe(TransferStatus::Rejected)
        ->and($transfer->rejectionReason())->toBe('Tiene deudas de documentos');
});

it('needs the reason of a rejection', function () {
    expect(fn () => requestedOn()->reject(InstitutionId::fromInt(4), 'Colegio Norte', ' ', new DateTimeImmutable('2026-10-10')))
        ->toThrow(BusinessRuleViolation::class, 'Indica el motivo del rechazo.');
});

it('is not answered once expired', function () {
    expect(fn () => requestedOn()->approve(InstitutionId::fromInt(4), 'Colegio Norte', new DateTimeImmutable('2026-10-17')))
        ->toThrow(BusinessRuleViolation::class, 'La solicitud caducó.');
});

it('is not answered by the institution that asked', function () {
    expect(fn () => requestedOn()->approve(InstitutionId::fromInt(9), 'Colegio Sur', new DateTimeImmutable('2026-10-10')))
        ->toThrow(BusinessRuleViolation::class, 'Una solicitud de tu plantel la responde el plantel de origen.');
});

it('is marked expired, and only once past its days', function () {
    $transfer = requestedOn();

    expect(fn () => $transfer->expire(new DateTimeImmutable('2026-10-16')))->toThrow(BusinessRuleViolation::class);

    $transfer->expire(new DateTimeImmutable('2026-10-17'));

    expect($transfer->status(new DateTimeImmutable('2026-10-17')))->toBe(TransferStatus::Expired);
});

it('is answered only once', function () {
    $transfer = requestedOn();
    $transfer->approve(InstitutionId::fromInt(4), 'Colegio Norte', new DateTimeImmutable('2026-10-10'));

    expect(fn () => $transfer->reject(InstitutionId::fromInt(4), 'Colegio Norte', 'No', new DateTimeImmutable('2026-10-11')))
        ->toThrow(BusinessRuleViolation::class, 'La solicitud ya fue respondida.');
});
