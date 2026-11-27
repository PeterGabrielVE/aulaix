<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Files;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\Files\StoredImage;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LogicException;

/**
 * Institution files on a Laravel disk (private local by default, S3 in
 * production), each institution in `institutions/{id}/`.
 */
final class LaravelInstitutionFiles implements InstitutionFiles
{
    private const array MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
    ];

    public function __construct(
        private readonly Filesystem $disk,
        private readonly CurrentTenant $tenant,
    ) {}

    public function store(AssetKind $kind, ImageUpload $image): string
    {
        $path = $this->folder($this->institution()).$kind->value.'-'.Str::random(40).'.'.$image->extension();

        if (! $this->disk->put($path, $image->contents)) {
            throw new LogicException("The file {$path} could not be stored.");
        }

        return $path;
    }

    public function read(string $path): ?StoredImage
    {
        if (! $this->isOwn($path)) {
            return null;
        }

        $contents = $this->disk->get($path);

        return $contents === null ? null : new StoredImage($contents, self::MIME_TYPES[pathinfo($path, PATHINFO_EXTENSION)]);
    }

    public function delete(string $path): void
    {
        if ($this->isOwn($path)) {
            $this->disk->delete($path);
        }
    }

    public function usedBytes(): int
    {
        return array_sum(array_map(
            fn (string $path) => $this->disk->size($path),
            $this->disk->allFiles($this->folder($this->institution())),
        ));
    }

    /**
     * Only the exact shape store() generates: no other folder, no `..`, no
     * other extension.
     */
    private function isOwn(string $path): bool
    {
        $institution = $this->tenant->institutionId();

        return $institution !== null
            && preg_match('#^'.preg_quote($this->folder($institution), '#').'[a-z-]+-[A-Za-z0-9]{40}\.(png|jpg)$#D', $path) === 1;
    }

    private function folder(InstitutionId $institution): string
    {
        return 'institutions/'.$institution->value.'/';
    }

    private function institution(): InstitutionId
    {
        return $this->tenant->institutionId()
            ?? throw new LogicException('Institution files need an active institution.');
    }
}
