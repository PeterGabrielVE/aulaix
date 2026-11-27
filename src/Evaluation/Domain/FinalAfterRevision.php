<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The final grade that counts for the year (RVP-R02.1, R02.2): the final
 * grade itself when it passed; when it failed, its revision decides. A
 * revision rounded to 10 or more passes, with that grade; less, or NP,
 * leaves the final failed. Exact integer arithmetic (RVP-NF03).
 */
final readonly class FinalAfterRevision
{
    public const string APPROVED = 'approved';

    public const string FAILED = 'failed';

    public const string NEEDS_REVISION = 'needs_revision';

    /**
     * @param  string  $state  approved, failed, needs_revision, incomplete or not_applicable.
     * @param  ?int  $score  The grade that counts, when decided.
     */
    private function __construct(
        public string $state,
        public ?int $score,
    ) {}

    public static function of(FinalScore $final, ?GradeMark $revision): self
    {
        if (! $final->isComplete()) {
            return new self($final->state, null);
        }

        if (! $final->failed()) {
            return new self(self::APPROVED, $final->score);
        }

        if ($revision === null) {
            return new self(self::NEEDS_REVISION, null);
        }

        $revised = $revision->isNotPresented() ? 0 : Rounding::nearest($revision->value(), 100);

        return $revised >= FinalScore::PASS
            ? new self(self::APPROVED, $revised)
            : new self(self::FAILED, $final->score);
    }

    public function passed(): bool
    {
        return $this->state === self::APPROVED;
    }

    public function failed(): bool
    {
        return $this->state === self::FAILED;
    }

    /**
     * Whether it is known to pass or fail.
     */
    public function isDecided(): bool
    {
        return $this->passed() || $this->failed();
    }
}
