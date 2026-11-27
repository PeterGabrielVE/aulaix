<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Transfer;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Domain\Identity\StudentIdentification;
use DateTimeImmutable;

/**
 * A destination institution asks for a student by its identification
 * (EST-R07.1). The request names no origin: whichever institution has that
 * student active sees it and answers it (ADR 0008), within 15 calendar days
 * (EST-R07.7).
 */
final class TransferRequest
{
    /**
     * Calendar days the origin has to answer: on the 15th day after the
     * request it can still answer, from the 16th it has expired.
     */
    public const int DAYS_TO_ANSWER = 15;

    public const int REASON_MAX_LENGTH = 500;

    private function __construct(
        private ?TransferRequestId $id,
        private InstitutionId $destinationId,
        private StudentIdentification $identification,
        private string $destinationName,
        private TransferStatus $status,
        private ?InstitutionId $originInstitutionId,
        private ?string $originName,
        private ?string $rejectionReason,
        private DateTimeImmutable $requestedAt,
        private ?DateTimeImmutable $answeredAt,
    ) {}

    public static function request(InstitutionId $destination, StudentIdentification $identification, string $destinationName, DateTimeImmutable $now): self
    {
        return new self(null, $destination, $identification, $destinationName, TransferStatus::Pending, null, null, null, $now, null);
    }

    public static function reconstitute(
        TransferRequestId $id,
        InstitutionId $destinationId,
        StudentIdentification $identification,
        string $destinationName,
        TransferStatus $status,
        ?InstitutionId $originInstitutionId,
        ?string $originName,
        ?string $rejectionReason,
        DateTimeImmutable $requestedAt,
        ?DateTimeImmutable $answeredAt,
    ): self {
        return new self($id, $destinationId, $identification, $destinationName, $status, $originInstitutionId, $originName, $rejectionReason, $requestedAt, $answeredAt);
    }

    public function id(): ?TransferRequestId
    {
        return $this->id;
    }

    public function identify(TransferRequestId $id): void
    {
        $this->id = $id;
    }

    public function destinationId(): InstitutionId
    {
        return $this->destinationId;
    }

    public function identification(): StudentIdentification
    {
        return $this->identification;
    }

    public function destinationName(): string
    {
        return $this->destinationName;
    }

    public function originInstitutionId(): ?InstitutionId
    {
        return $this->originInstitutionId;
    }

    public function originName(): ?string
    {
        return $this->originName;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function answeredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }

    /**
     * As kept: a pending request past its days still reads Pending here.
     */
    public function storedStatus(): TransferStatus
    {
        return $this->status;
    }

    /**
     * The stored status, or Expired for a pending request past its days.
     */
    public function status(DateTimeImmutable $now): TransferStatus
    {
        return $this->status === TransferStatus::Pending && $this->isPastDeadline($now) ? TransferStatus::Expired : $this->status;
    }

    /**
     * The last calendar day the origin can answer.
     */
    public function lastDayToAnswer(): DateTimeImmutable
    {
        return $this->requestedAt->setTime(0, 0)->modify('+'.self::DAYS_TO_ANSWER.' days');
    }

    /**
     * @throws BusinessRuleViolation When it was answered, has expired or is the origin's own.
     */
    public function approve(InstitutionId $origin, string $originName, DateTimeImmutable $now): void
    {
        $this->assertAnswerableBy($origin, $now);

        $this->status = TransferStatus::Approved;
        $this->originInstitutionId = $origin;
        $this->originName = $originName;
        $this->answeredAt = $now;
    }

    /**
     * @throws BusinessRuleViolation When it was answered, has expired, is the origin's own or has no reason.
     */
    public function reject(InstitutionId $origin, string $originName, string $reason, DateTimeImmutable $now): void
    {
        $this->assertAnswerableBy($origin, $now);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw BusinessRuleViolation::onField('rejection_reason', 'Indica el motivo del rechazo.');
        }

        $this->status = TransferStatus::Rejected;
        $this->originInstitutionId = $origin;
        $this->originName = $originName;
        $this->rejectionReason = $reason;
        $this->answeredAt = $now;
    }

    /**
     * Stores the expiry of a pending request past its days, so the
     * destination can ask for the student again.
     *
     * @throws BusinessRuleViolation When it is not pending or not past its days.
     */
    public function expire(DateTimeImmutable $now): void
    {
        if ($this->status !== TransferStatus::Pending || ! $this->isPastDeadline($now)) {
            throw BusinessRuleViolation::onField('status', 'La solicitud aún está vigente.');
        }

        $this->status = TransferStatus::Expired;
    }

    private function assertAnswerableBy(InstitutionId $origin, DateTimeImmutable $now): void
    {
        if ($origin->equals($this->destinationId)) {
            throw BusinessRuleViolation::onField('status', 'Una solicitud de tu plantel la responde el plantel de origen.');
        }

        if ($this->status !== TransferStatus::Pending) {
            throw BusinessRuleViolation::onField('status', 'La solicitud ya fue respondida.');
        }

        if ($this->isPastDeadline($now)) {
            throw BusinessRuleViolation::onField('status', 'La solicitud caducó.');
        }
    }

    private function isPastDeadline(DateTimeImmutable $now): bool
    {
        return $now->setTime(0, 0) > $this->lastDayToAnswer();
    }
}
