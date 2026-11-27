<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Optional plain-text observation on a good-conduct certificate (SPEC-012
 * DOC-R04.4, R04.6): up to 300 characters, omitted when blank, never exposed
 * on the public QR verification page (DOC-R07.3).
 */
final readonly class GoodConductNote
{
    public const int MAX_LENGTH = 300;

    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromNullable(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\r\n?/', "\n", $raw) ?? '');

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw BusinessRuleViolation::onField('observation', 'La observación no puede superar los 300 caracteres.');
        }

        return new self($normalized);
    }
}
