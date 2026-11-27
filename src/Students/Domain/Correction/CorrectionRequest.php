<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Correction;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Student\StudentId;
use DateTimeImmutable;

/**
 * A guardian asks the institution to correct a datum of its own record or of
 * one of its students (EST-R08.4); a manager marks it attended (EST-R08.5).
 * The correction itself is made on the records, by the manager.
 */
final class CorrectionRequest
{
    public const int TEXT_MAX_LENGTH = 1000;

    private function __construct(
        private ?CorrectionRequestId $id,
        private GuardianId $guardianId,
        private ?StudentId $studentId,
        private string $text,
        private CorrectionStatus $status,
        private DateTimeImmutable $submittedAt,
        private ?DateTimeImmutable $resolvedAt,
    ) {}

    /**
     * @throws BusinessRuleViolation When the text is empty or too long.
     */
    public static function submit(GuardianId $guardianId, ?StudentId $studentId, string $text, DateTimeImmutable $now): self
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > self::TEXT_MAX_LENGTH) {
            throw BusinessRuleViolation::onField('text', 'Escribe qué dato hay que corregir, en '.self::TEXT_MAX_LENGTH.' caracteres como máximo.');
        }

        return new self(null, $guardianId, $studentId, $text, CorrectionStatus::Pending, $now, null);
    }

    public static function reconstitute(
        CorrectionRequestId $id,
        GuardianId $guardianId,
        ?StudentId $studentId,
        string $text,
        CorrectionStatus $status,
        DateTimeImmutable $submittedAt,
        ?DateTimeImmutable $resolvedAt,
    ): self {
        return new self($id, $guardianId, $studentId, $text, $status, $submittedAt, $resolvedAt);
    }

    public function id(): ?CorrectionRequestId
    {
        return $this->id;
    }

    public function identify(CorrectionRequestId $id): void
    {
        $this->id = $id;
    }

    public function guardianId(): GuardianId
    {
        return $this->guardianId;
    }

    public function studentId(): ?StudentId
    {
        return $this->studentId;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function status(): CorrectionStatus
    {
        return $this->status;
    }

    public function submittedAt(): DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /**
     * @throws BusinessRuleViolation When it was already attended.
     */
    public function resolve(DateTimeImmutable $now): void
    {
        if ($this->status === CorrectionStatus::Resolved) {
            throw BusinessRuleViolation::onField('status', 'La solicitud ya está atendida.');
        }

        $this->status = CorrectionStatus::Resolved;
        $this->resolvedAt = $now;
    }
}
