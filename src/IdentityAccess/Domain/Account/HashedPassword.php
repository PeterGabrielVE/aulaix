<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use InvalidArgumentException;
use Stringable;

/**
 * An already hashed password. The domain never sees the plain password, and
 * this value never shows its hash when printed or dumped, so it can't end up
 * in a log or an error message by accident. Read it explicitly with value().
 */
final class HashedPassword implements Stringable
{
    private function __construct(#[\SensitiveParameter] private readonly string $hash) {}

    public static function fromHash(#[\SensitiveParameter] string $hash): self
    {
        if ($hash === '') {
            throw new InvalidArgumentException('A password hash can not be empty.');
        }

        return new self($hash);
    }

    public function value(): string
    {
        return $this->hash;
    }

    public function __toString(): string
    {
        return '[hidden]';
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['hash' => '[hidden]'];
    }
}
