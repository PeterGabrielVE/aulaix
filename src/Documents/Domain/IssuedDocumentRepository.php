<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

/**
 * Persistence contract for issued documents, scoped to the open institution
 * by PostgreSQL Row Level Security (SPEC-012 DOC-R10.1, S-01).
 */
interface IssuedDocumentRepository
{
    public function save(IssuedDocument $document): DocumentId;

    public function byId(DocumentId $id): ?IssuedDocument;

    public function byVerificationCodeHash(string $hash): ?IssuedDocument;
}
