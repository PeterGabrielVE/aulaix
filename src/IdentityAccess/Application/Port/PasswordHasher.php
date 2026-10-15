<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\HashedPassword;

/**
 * Hashes a plain password, so the domain only ever holds the hash.
 */
interface PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $plainPassword): HashedPassword;
}
