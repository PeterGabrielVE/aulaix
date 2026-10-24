<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Source;

use AulaX\Catalogs\Application\Port\SubjectSource;
use AulaX\Catalogs\Domain\EducationLevel;
use AulaX\Catalogs\Domain\Subject\SubjectCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectEntry;

/**
 * The MPPE (Ministerio del Poder Popular para la Educación) subjects catalog.
 *
 * Educación Media General uses the "áreas de formación" of the 2017
 * Proceso de Transformación Curricular, which replaced the older separate
 * Historia de Venezuela / Historia Universal / Geografía de Venezuela with
 * Geografía, Historia y Ciudadanía. Primaria and Inicial use the "áreas de
 * aprendizaje" of the Currículo Nacional Bolivariano. Which year (1.º–5.º
 * año, 1.º–6.º grado) teaches each one is not modeled here yet.
 */
final class MppeSubjectSource implements SubjectSource
{
    private const SUBJECTS = [
        EducationLevel::Inicial->value => [
            'INI-FPSC' => 'Formación Personal, Social y Comunicación',
            'INI-RAMB' => 'Relación con el Ambiente',
        ],
        EducationLevel::Primaria->value => [
            'PRI-LCC' => 'Lenguaje, Comunicación y Cultura',
            'PRI-MCNS' => 'Matemática, Ciencias Naturales y Sociedad',
            'PRI-CSCI' => 'Ciencias Sociales, Ciudadanía e Identidad',
            'PRI-EFDR' => 'Educación Física, Deportes y Recreación',
        ],
        EducationLevel::MediaGeneral->value => [
            'CAST' => 'Castellano',
            'ING' => 'Inglés y otras Lenguas Extranjeras',
            'MAT' => 'Matemáticas',
            'EDF' => 'Educación Física',
            'ART' => 'Arte y Patrimonio',
            'CNAT' => 'Ciencias Naturales',
            'FIS' => 'Física',
            'QUI' => 'Química',
            'BIO' => 'Biología',
            'CTIE' => 'Ciencias de la Tierra',
            'GHC' => 'Geografía, Historia y Ciudadanía',
            'FSN' => 'Formación para la Soberanía Nacional',
            'OYC' => 'Orientación y Convivencia',
            'GCRP' => 'Participación en Grupos de Creación, Recreación y Producción',
        ],
    ];

    public function load(): SubjectCatalog
    {
        $entries = [];

        foreach (self::SUBJECTS as $level => $subjects) {
            foreach ($subjects as $code => $name) {
                $entries[] = SubjectEntry::of($code, $name, EducationLevel::from($level));
            }
        }

        return SubjectCatalog::of(...$entries);
    }
}
