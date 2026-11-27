<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

/**
 * An email address, normalized before it is stored or compared (IDG-R01.2):
 * trimmed and lower-cased. No provider-specific rules (dots and +tags in
 * Gmail are kept), so two addresses are the same only if they really are.
 */
final readonly class EmailAddress
{
    private const MAX_LENGTH = 255;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $email = mb_strtolower(trim($value));

        if (strlen($email) > self::MAX_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddress::of($email);
        }

        return new self($email);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
