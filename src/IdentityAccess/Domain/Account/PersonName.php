<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use AulaX\Shared\Domain\BusinessRuleViolation;

final readonly class PersonName
{
    private const MAX_LENGTH = 255;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $name = trim($value);

        if ($name === '' || mb_strlen($name) > self::MAX_LENGTH) {
            throw BusinessRuleViolation::onField('name', 'El nombre es obligatorio y no puede superar 255 caracteres.');
        }

        return new self($name);
    }

    /**
     * Whether two names belong, by all appearances, to the same person:
     * compared ignoring case and repeated spaces. Used to tell duplicate
     * historical accounts that can be consolidated by their owner from those
     * that need a human review (IDG-R09.5).
     */
    public function sameAs(self $other): bool
    {
        return self::normalized($this->value) === self::normalized($other->value);
    }

    private static function normalized(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
