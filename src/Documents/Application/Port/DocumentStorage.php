<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

/**
 * Private per-institution PDF storage (SPEC-012 DOC-R05.4, R10.1, NF04;
 * ADR 0015). Paths live strictly under `institutions/{id}/documents/`.
 */
interface DocumentStorage
{
    /**
     * Stores immutable PDF bytes under a system-generated path.
     */
    public function store(string $pdfBytes): string;

    /**
     * Reads PDF bytes at $path, or null when missing or outside the active
     * institution's folder.
     */
    public function read(string $path): ?string;

    /**
     * Deletes a PDF at $path (used only to clean up on transaction rollback),
     * when it belongs to the active institution.
     */
    public function delete(string $path): void;
}
