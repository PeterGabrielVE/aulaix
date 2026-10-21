<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Port;

use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\Files\StoredImage;

/**
 * The private files of the active institution: its logo and signatures.
 * Every path lives in the active institution's own folder; a path of any
 * other folder is never read nor deleted, whoever writes it (INS-R07.2).
 */
interface InstitutionFiles
{
    /**
     * Stores the image under a name the system generates.
     *
     * @return string The path to keep with the institution.
     */
    public function store(AssetKind $kind, ImageUpload $image): string;

    /**
     * The image at this path, or null when it is missing or not the active
     * institution's.
     */
    public function read(string $path): ?StoredImage;

    /**
     * Deletes the image at this path, if it is the active institution's.
     */
    public function delete(string $path): void;
}
