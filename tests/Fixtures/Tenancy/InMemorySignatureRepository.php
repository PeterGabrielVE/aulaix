<?php

namespace Tests\Fixtures\Tenancy;

use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;

/**
 * The open institution's signatures, kept in memory by role.
 */
final class InMemorySignatureRepository implements SignatureRepository
{
    /** @var array<string, Signature> */
    public array $signatures = [];

    public function of(SignatureRole $role): ?Signature
    {
        return $this->signatures[$role->value] ?? null;
    }

    public function all(): array
    {
        return array_values(array_filter(array_map(fn (SignatureRole $role) => $this->of($role), SignatureRole::cases())));
    }

    public function save(Signature $signature): void
    {
        $this->signatures[$signature->role->value] = $signature;
    }

    public function delete(SignatureRole $role): void
    {
        unset($this->signatures[$role->value]);
    }
}
