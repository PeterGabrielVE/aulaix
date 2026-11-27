<?php

namespace Tests\Fixtures\Tenancy;

use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\Files\StoredImage;
use AulaX\Tenancy\Application\Port\InstitutionFiles;

/**
 * The open institution's files, kept in memory under predictable names.
 */
final class InMemoryInstitutionFiles implements InstitutionFiles
{
    /** @var array<string, StoredImage> */
    public array $files = [];

    private int $stored = 0;

    public function store(AssetKind $kind, ImageUpload $image): string
    {
        $path = 'institutions/1/'.$kind->value.'-'.++$this->stored.'.'.$image->extension();
        $this->files[$path] = new StoredImage($image->contents, $image->mimeType);

        return $path;
    }

    public function read(string $path): ?StoredImage
    {
        return $this->files[$path] ?? null;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }

    public function usedBytes(): int
    {
        return array_sum(array_map(fn (StoredImage $file) => strlen($file->contents), $this->files));
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_keys($this->files);
    }
}
