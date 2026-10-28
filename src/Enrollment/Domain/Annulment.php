<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;

/**
 * Who annulled an enrollment made by mistake, when and why (MAT-R14.1,
 * R14.4, R14.6). Another reason needs a note.
 */
final readonly class Annulment
{
    public const int NOTE_MAX_LENGTH = 500;

    private function __construct(
        public AnnulmentReason $reason,
        public ?string $note,
        public int $annulledBy,
        public DateTimeImmutable $annulledAt,
    ) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function of(?AnnulmentReason $reason, ?string $note, int $annulledBy, DateTimeImmutable $annulledAt): self
    {
        if ($reason === null) {
            throw BusinessRuleViolation::onField('reason', 'Indica el motivo de la anulación.');
        }

        $note = trim((string) $note);

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH || ($reason === AnnulmentReason::Other && $note === '')) {
            throw BusinessRuleViolation::onField('note', 'Describe el motivo de la anulación.');
        }

        return new self($reason, $note === '' ? null : $note, $annulledBy, $annulledAt);
    }

    public static function reconstitute(AnnulmentReason $reason, ?string $note, int $annulledBy, DateTimeImmutable $annulledAt): self
    {
        return new self($reason, $note, $annulledBy, $annulledAt);
    }
}
