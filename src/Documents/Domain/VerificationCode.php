<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

/**
 * Opaque, non-sequential 128-bit verification code printed on each PDF and
 * embedded in its public QR URL (SPEC-012 DOC-R07.1, NF02; ADR 0015).
 *
 * Encoded in Crockford Base32 (26 characters, no ambiguous O/I/L/U) for
 * human legibility. Only its SHA-256 hash and a non-invertible 6-character
 * suffix hint are stored in the database.
 */
final readonly class VerificationCode
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int LENGTH = 26;

    private function __construct(public string $normalized) {}

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bits = '';

        for ($i = 0; $i < 16; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        // 128 bits -> 26 groups of 5 bits (2 padding zero bits on the right).
        $bits = str_pad($bits, self::LENGTH * 5, '0');
        $encoded = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $encoded .= self::ALPHABET[bindec(substr($bits, $i * 5, 5))];
        }

        return new self($encoded);
    }

    /**
     * Normalizes user-typed or URL input, returning null when it cannot be a
     * valid verification code.
     */
    public static function tryNormalize(string $raw): ?string
    {
        $clean = strtoupper(preg_replace('/[\s\-]+/', '', trim($raw)) ?? '');

        if (strlen($clean) !== self::LENGTH) {
            return null;
        }

        if (strspn($clean, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $clean;
    }

    public static function hashRaw(string $raw): ?string
    {
        $normalized = self::tryNormalize($raw);

        return $normalized === null ? null : hash('sha256', $normalized);
    }

    public function hash(): string
    {
        return hash('sha256', $this->normalized);
    }

    /**
     * Non-invertible suffix shown in the internal history table.
     */
    public function hint(): string
    {
        return '…'.substr($this->normalized, -6);
    }

    /**
     * Grouped representation printed on the PDF for manual entry.
     */
    public function formatted(): string
    {
        return implode('-', [
            substr($this->normalized, 0, 5),
            substr($this->normalized, 5, 5),
            substr($this->normalized, 10, 5),
            substr($this->normalized, 15, 5),
            substr($this->normalized, 20, 6),
        ]);
    }
}
