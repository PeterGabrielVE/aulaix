<?php

namespace Database\Seeders;

use App\Enums\EducationLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeds the global MPPE (Ministerio del Poder Popular para la Educación)
 * subjects catalog (F1-04), shared by every institution.
 *
 * Educación Media General uses the "áreas de formación" of the 2017
 * Proceso de Transformación Curricular, which replaced the older separate
 * Historia de Venezuela / Historia Universal / Geografía de Venezuela with
 * Geografía, Historia y Ciudadanía. Primaria and Inicial use the "áreas de
 * aprendizaje" of the Currículo Nacional Bolivariano. Which year (1.º–5.º
 * año, 1.º–6.º grado) teaches each one is not modeled here yet.
 *
 * The seeder is the source of truth: re-running it renames subjects by
 * code and removes codes no longer in the catalog.
 */
class SubjectSeeder extends Seeder
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

    public function run(): void
    {
        $rows = [];

        foreach (self::SUBJECTS as $level => $subjects) {
            foreach ($subjects as $code => $name) {
                $rows[] = ['code' => $code, 'name' => $name, 'education_level' => $level];
            }
        }

        Subject::query()->upsert($rows, uniqueBy: ['code'], update: ['name', 'education_level']);

        Subject::query()->whereNotIn('code', array_column($rows, 'code'))->delete();
    }
}
