<?php

use App\Models\Institution;
use App\Support\RowLevelSecurity;
use AulaX\Catalogs\Domain\EducationLevel;
use AulaX\Catalogs\Infrastructure\Persistence\MunicipalityModel as Municipality;
use AulaX\Catalogs\Infrastructure\Persistence\ParishModel as Parish;
use AulaX\Catalogs\Infrastructure\Persistence\StateModel as State;
use AulaX\Catalogs\Infrastructure\Persistence\SubjectModel as Subject;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1-04: states/municipalities/parishes and MPPE subjects are global
 * catalogs — no institution_id, visible to every tenant, not RLS-scoped —
 * seeded once and shared by every institution.
 */
const CATALOG_TABLES = ['states', 'municipalities', 'parishes', 'subjects'];

test('the geographic catalog covers all of Venezuela', function () {
    $this->seed(CatalogSeeder::class);

    expect(State::count())->toBe(24);
    expect(Municipality::count())->toBe(335);
    expect(Parish::count())->toBe(1140);

    // Every municipality has at least one parish, every state a municipality.
    expect(Municipality::doesntHave('parishes')->count())->toBe(0);
    expect(State::doesntHave('municipalities')->count())->toBe(0);
});

test('states use their ISO 3166-2 code and current name', function () {
    $this->seed(CatalogSeeder::class);

    expect(State::where('code', 'VE-A')->value('name'))->toBe('Distrito Capital');
    expect(State::where('code', 'VE-V')->value('name'))->toBe('Zulia');
    expect(State::where('code', 'VE-W')->value('name'))->toBe('La Guaira');
    expect(State::where('name', 'Vargas')->exists())->toBeFalse();
});

test('a state exposes its municipalities and parishes', function () {
    $this->seed(CatalogSeeder::class);

    $zulia = State::where('name', 'Zulia')->firstOrFail();
    $libertador = Municipality::where('code', 'like', 'VE-A-%')->where('name', 'Libertador')->firstOrFail();

    expect($zulia->municipalities)->toHaveCount(21);
    expect($libertador->parishes->pluck('name'))->toContain('Catedral', 'Caricuao', '23 de enero');
});

test('seeding the catalogs again changes nothing', function () {
    $this->seed(CatalogSeeder::class);
    $before = array_map(fn ($table) => DB::table($table)->orderBy('id')->get(['id', 'code', 'name'])->all(), CATALOG_TABLES);

    $this->seed(CatalogSeeder::class);
    $after = array_map(fn ($table) => DB::table($table)->orderBy('id')->get(['id', 'code', 'name'])->all(), CATALOG_TABLES);

    expect($after)->toEqual($before);
});

test('the catalogs are the same from any institution and with no institution at all', function () {
    $this->seed(CatalogSeeder::class);
    $counts = fn () => array_map(fn ($table) => DB::table($table)->count(), CATALOG_TABLES);
    $expected = [24, 335, 1140, Subject::count()];

    tenant();
    expect($counts())->toBe($expected);

    tenant();
    expect($counts())->toBe($expected);

    RowLevelSecurity::clearInstitution();
    expect($counts())->toBe($expected);
});

test('catalog tables are not tenant-scoped', function (string $table) {
    $rls = DB::selectOne('select relrowsecurity from pg_class where relname = ?', [$table]);

    expect(Schema::hasColumn($table, 'institution_id'))->toBeFalse();
    expect($rls->relrowsecurity)->toBeFalse();
})->with(CATALOG_TABLES);

test('the placeholder catalog of the first version is replaced, keeping institutions', function () {
    // The old seed: one placeholder state/municipality/parish, coded VE-01.
    $state = State::create(['code' => 'VE-01', 'name' => 'Amazonas']);
    $municipality = $state->municipalities()->create(['code' => 'VE-01-01', 'name' => 'Puerto Ayacucho']);
    $parish = $municipality->parishes()->create(['code' => 'VE-01-01-01', 'name' => 'Puerto Ayacucho']);
    $institution = Institution::factory()->create(['parish_id' => $parish->id]);

    $this->seed(CatalogSeeder::class);

    expect(State::where('name', 'Amazonas')->pluck('code')->all())->toBe(['VE-X']);
    expect(Parish::where('code', 'like', 'VE-01%')->exists())->toBeFalse();
    expect($institution->fresh()->parish_id)->toBeNull();
});

test('the subjects catalog follows the current MPPE curriculum', function () {
    $this->seed(CatalogSeeder::class);

    expect(Subject::where('code', 'GHC')->value('name'))->toBe('Geografía, Historia y Ciudadanía');
    expect(Subject::where('code', 'MAT')->first()->education_level)->toBe(EducationLevel::MediaGeneral);

    foreach (EducationLevel::cases() as $level) {
        expect(Subject::where('education_level', $level)->exists())->toBeTrue("Sin materias para {$level->value}");
    }
});

test('subjects dropped from the curriculum are removed when re-seeding', function () {
    Subject::create(['code' => 'HVE', 'name' => 'Historia de Venezuela', 'education_level' => EducationLevel::MediaGeneral]);
    Subject::create(['code' => 'CAST', 'name' => 'Castellano y Literatura', 'education_level' => EducationLevel::MediaGeneral]);

    $this->seed(CatalogSeeder::class);

    expect(Subject::where('code', 'HVE')->exists())->toBeFalse();
    expect(Subject::where('code', 'CAST')->value('name'))->toBe('Castellano');
});
