<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

/**
 * The signatures of the active institution (Row Level Security limits every
 * read and write to it), one per role.
 */
interface SignatureRepository
{
    public function of(SignatureRole $role): ?Signature;

    /**
     * @return list<Signature> Director first.
     */
    public function all(): array;

    /**
     * Creates or replaces the signature of its role.
     */
    public function save(Signature $signature): void;

    public function delete(SignatureRole $role): void;
}
