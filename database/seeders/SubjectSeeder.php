<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeds the global MPPE (Ministerio del Poder Popular para la Educación)
 * subjects catalog (F1-04) for Educación Media General.
 */
class SubjectSeeder extends Seeder
{
    private const SUBJECTS = [
        ['code' => 'CAST', 'name' => 'Castellano y Literatura'],
        ['code' => 'MAT', 'name' => 'Matemática'],
        ['code' => 'BIO', 'name' => 'Biología'],
        ['code' => 'FIS', 'name' => 'Física'],
        ['code' => 'QUI', 'name' => 'Química'],
        ['code' => 'HVE', 'name' => 'Historia de Venezuela'],
        ['code' => 'HUN', 'name' => 'Historia Universal'],
        ['code' => 'GEO', 'name' => 'Geografía de Venezuela'],
        ['code' => 'ING', 'name' => 'Inglés'],
        ['code' => 'EDF', 'name' => 'Educación Física'],
        ['code' => 'ART', 'name' => 'Arte y Patrimonio'],
        ['code' => 'FSN', 'name' => 'Formación para la Soberanía Nacional'],
    ];

    public function run(): void
    {
        foreach (self::SUBJECTS as $subject) {
            Subject::query()->updateOrCreate(
                ['code' => $subject['code']],
                ['name' => $subject['name'], 'education_level' => 'media_general'],
            );
        }
    }
}
