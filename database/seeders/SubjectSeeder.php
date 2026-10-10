<?php

namespace Database\Seeders;

use AulaX\Catalogs\Application\SyncSubjectsHandler;
use Illuminate\Database\Seeder;

/**
 * Seeds the global MPPE (Ministerio del Poder Popular para la Educación)
 * subjects catalog (F1-04), shared by every institution.
 *
 * A thin entry point: the curriculum lives in
 * AulaX\Catalogs\Infrastructure\Source\MppeSubjectSource, and the sync rules
 * (authoritative: renames by code, removes codes no longer in the catalog) in
 * AulaX\Catalogs\Application\SyncSubjectsHandler.
 */
class SubjectSeeder extends Seeder
{
    public function run(SyncSubjectsHandler $syncSubjects): void
    {
        $syncSubjects();
    }
}
