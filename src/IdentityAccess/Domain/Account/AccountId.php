<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use InvalidArgumentException;

final readonly class AccountId
{
    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("Invalid account id: {$value}.");
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
