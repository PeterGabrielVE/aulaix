<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Files;

use AulaX\Tenancy\Application\Port\InstitutionFiles;
use Throwable;

/**
 * The disk is not transactional, so a new file is stored before the
 * transaction and the old one deleted after it; if the transaction fails,
 * the new file goes instead. No path is ever left without its file.
 */
final class FileReplacement
{
    /**
     * @param  callable(): ?string  $persist  The transaction; returns the path the new file replaces, if any.
     */
    public static function commit(InstitutionFiles $files, string $newPath, callable $persist): void
    {
        try {
            $replaced = $persist();
        } catch (Throwable $failure) {
            $files->delete($newPath);

            throw $failure;
        }

        if ($replaced !== null && $replaced !== $newPath) {
            $files->delete($replaced);
        }
    }
}
