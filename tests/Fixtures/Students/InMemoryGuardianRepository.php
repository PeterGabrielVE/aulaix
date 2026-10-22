<?php

namespace Tests\Fixtures\Students;

use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Identity\GuardianDocument;

/**
 * The guardians of one institution, kept in memory.
 */
final class InMemoryGuardianRepository implements GuardianRepository
{
    /** @var array<int, Guardian> */
    public array $guardians = [];

    private int $nextId = 1;

    public function byId(GuardianId $id): ?Guardian
    {
        return isset($this->guardians[$id->value]) ? clone $this->guardians[$id->value] : null;
    }

    public function byDocument(GuardianDocument $document): ?Guardian
    {
        foreach ($this->guardians as $guardian) {
            if ($guardian->data()->document->equals($document)) {
                return clone $guardian;
            }
        }

        return null;
    }

    public function save(Guardian $guardian): GuardianId
    {
        $id = $guardian->id() ?? GuardianId::fromInt($this->nextId++);
        $guardian->identify($id);
        $this->guardians[$id->value] = clone $guardian;

        return $id;
    }
}
