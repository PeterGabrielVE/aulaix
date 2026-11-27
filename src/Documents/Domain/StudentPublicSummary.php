<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

/**
 * Minimal student identifier shown on the public QR verification page
 * (SPEC-012 DOC-R07.2, R07.3): uppercase initials and last 4 digits of the
 * national or school ID — never the full name or full ID of a minor.
 */
final readonly class StudentPublicSummary
{
    private function __construct(
        public string $initials,
        public string $idLast4,
    ) {}

    public static function fromStudent(string $givenNames, string $familyNames, string $identification): self
    {
        $words = preg_split('/\s+/u', trim("{$givenNames} {$familyNames}"), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = implode(' ', array_map(
            fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)).'.',
            $words,
        ));

        $digits = preg_replace('/\D+/', '', $identification) ?? '';
        $idLast4 = strlen($digits) >= 4 ? '****'.substr($digits, -4) : '—';

        return new self($initials !== '' ? $initials : '—', $idLast4);
    }

    public static function reconstitute(string $initials, string $idLast4): self
    {
        return new self($initials, $idLast4);
    }
}
