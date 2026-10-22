<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Guardian;

use AulaX\Shared\Domain\Conflict;
use AulaX\Students\Domain\Identity\GuardianDocument;

/**
 * The guardians of the open institution (Row Level Security limits every
 * read and write to it).
 */
interface GuardianRepository
{
    public function byId(GuardianId $id): ?Guardian;

    /**
     * The guardian of the institution with this document, so siblings share
     * it (EST-R03.3).
     */
    public function byDocument(GuardianDocument $document): ?Guardian;

    /**
     * @throws Conflict When another guardian of the institution has its document.
     */
    public function save(Guardian $guardian): GuardianId;
}
