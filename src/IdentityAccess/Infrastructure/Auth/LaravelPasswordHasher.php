<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Domain\Account\HashedPassword;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private Hasher $hasher) {}

    public function hash(#[\SensitiveParameter] string $plainPassword): HashedPassword
    {
        return HashedPassword::fromHash($this->hasher->make($plainPassword));
    }
}
