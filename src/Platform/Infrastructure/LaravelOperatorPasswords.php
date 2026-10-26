<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\OperatorPasswords;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Laravel's hasher (bcrypt, PLT-NF02).
 */
final class LaravelOperatorPasswords implements OperatorPasswords
{
    private ?string $dummyHash = null;

    public function __construct(private readonly Hasher $hasher) {}

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return $this->hasher->make($password);
    }

    public function check(#[\SensitiveParameter] string $password, ?string $hash): bool
    {
        // V5: an unknown operator costs one comparison too.
        $matches = $this->hasher->check($password, $hash ?? ($this->dummyHash ??= $this->hasher->make(bin2hex(random_bytes(16)))));

        return $hash !== null && $matches;
    }
}
