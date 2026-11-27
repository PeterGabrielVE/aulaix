<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Storage;

use AulaX\Documents\Application\Port\DocumentStorage;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LogicException;

/**
 * Stores immutable issued PDFs on the institution's private disk under
 * `institutions/{id}/documents/{random40}.pdf`, so `UsedStorageQuery`
 * (SPEC-014 PLT-R04.3) counts them automatically while `isOwn()` enforces
 * strict path isolation (DOC-R10.1, NF04).
 */
final readonly class LaravelDocumentStorage implements DocumentStorage
{
    public function __construct(
        private Filesystem $disk,
        private CurrentTenant $tenant,
    ) {}

    public function store(string $pdfBytes): string
    {
        $path = $this->folder($this->institution()).Str::random(40).'.pdf';

        if (! $this->disk->put($path, $pdfBytes)) {
            throw new LogicException("The document {$path} could not be stored.");
        }

        return $path;
    }

    public function read(string $path): ?string
    {
        if (! $this->isOwn($path)) {
            return null;
        }

        return $this->disk->get($path);
    }

    public function delete(string $path): void
    {
        if ($this->isOwn($path)) {
            $this->disk->delete($path);
        }
    }

    private function isOwn(string $path): bool
    {
        $institution = $this->tenant->institutionId();

        return $institution !== null
            && preg_match('#^'.preg_quote($this->folder($institution), '#').'[A-Za-z0-9]{40}\.pdf$#D', $path) === 1;
    }

    private function folder(InstitutionId $institution): string
    {
        return 'institutions/'.$institution->value.'/documents/';
    }

    private function institution(): InstitutionId
    {
        return $this->tenant->institutionId()
            ?? throw new LogicException('Document storage requires an active institution.');
    }
}
