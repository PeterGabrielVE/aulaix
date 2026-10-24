<?php

use AulaX\Catalogs\Application\Subjects;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 (public API of the catalog): the subjects of a level, and the
 * level of given subjects.
 */
function catalogSubject(string $code, string $name, string $level): int
{
    return DB::table('subjects')->insertGetId(['code' => $code, 'name' => $name, 'education_level' => $level, 'created_at' => now(), 'updated_at' => now()]);
}

it('lists the subjects of a level, by name', function () {
    $math = catalogSubject('T-PRI-MAT', 'Matemática', 'primaria');
    $arts = catalogSubject('T-PRI-ART', 'Artes', 'primaria');
    catalogSubject('T-MED-FIS', 'Física', 'media_general');

    expect(collect(app(Subjects::class)->ofLevel('primaria'))->whereIn('id', [$math, $arts])->values()->all())->toBe([
        ['id' => $arts, 'code' => 'T-PRI-ART', 'name' => 'Artes'],
        ['id' => $math, 'code' => 'T-PRI-MAT', 'name' => 'Matemática'],
    ]);
});

it('tells the level of each given subject, leaving out the unknown ones', function () {
    $math = catalogSubject('T-PRI-MAT', 'Matemática', 'primaria');
    $physics = catalogSubject('T-MED-FIS', 'Física', 'media_general');

    expect(app(Subjects::class)->levelsOf([$math, $physics, 999_999]))->toBe([$math => 'primaria', $physics => 'media_general']);
});
