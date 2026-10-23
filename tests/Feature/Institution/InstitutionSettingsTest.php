<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Tenancy\Images;

/**
 * SPEC-006 over HTTP: the institution screen, who may see and change it, and
 * how its images are served.
 */
beforeEach(function () {
    Storage::fake(config('tenancy.files_disk'));
});

function institutionForm(array $data = []): array
{
    return $data + [
        'name' => 'Colegio Norte',
        'dea_code' => 'OD-NORTE',
        'rif' => 'J-12345678-9',
        'parish_id' => null,
        'address' => 'Av. Principal',
        'phone' => '0212-5550000',
        'email' => 'info@norte.ve',
        'type' => 'privada',
        'levels' => ['primaria', 'media_general'],
    ];
}

function asMember(Institution $institution, string $role): object
{
    return test()->actingAs(userWithRole($institution, $role));
}

function uploadedImage(string $contents, string $name = 'imagen.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('the manager sees every detail of the open institution, signatures included', function () {
    $institution = tenant(['name' => 'Colegio Norte', 'type' => 'publica', 'education_levels' => ['inicial']]);

    asMember($institution, 'Administrador')->get(tenantUrl($institution, '/plantel'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institution/Settings')
            ->where('details.name', 'Colegio Norte')
            ->where('details.type', 'publica')
            ->where('details.levels', ['inicial'])
            ->where('canManage', true)
            ->has('details.signatures')
            ->has('options.levels', 3)
            ->has('options.types', 3)
        );
});

test('the Director sees the details without the signatures and cannot change them', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');

    $this->actingAs($director)->get(tenantUrl($institution, '/plantel'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false)->where('details.signatures', null));

    $this->actingAs($director)->put(tenantUrl($institution, '/plantel'), institutionForm())->assertForbidden();
    $this->actingAs($director)->post(tenantUrl($institution, '/plantel/logo'), ['file' => uploadedImage(Images::png())])->assertForbidden();
    $this->actingAs($director)->get(tenantUrl($institution, '/plantel/firmas/director'))->assertForbidden();
});

test('a member without ver-plantel cannot open the screen', function (string $role) {
    $institution = tenant();

    asMember($institution, $role)->get(tenantUrl($institution, '/plantel'))->assertForbidden();
})->with(['Coordinador', 'Docente']);

test('saving changes the open institution only, whatever the form names', function () {
    $institution = tenant(['name' => 'Colegio Viejo', 'subdomain' => 'norte']);
    $other = tenant(['name' => 'Colegio Sur']);
    useTenant($institution);

    asMember($institution, 'Administrador')
        ->put(tenantUrl($institution, '/plantel'), institutionForm(['institution_id' => $other->id, 'subdomain' => 'robado']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($institution->fresh())->name->toBe('Colegio Norte')->subdomain->toBe('norte')->type->toBe('privada')
        ->and($other->fresh()->name)->toBe('Colegio Sur');
});

test('a DEA code of another institution is refused without naming it', function () {
    tenant(['name' => 'Colegio Sur', 'dea_code' => 'OD-SUR']);
    $institution = tenant();

    $response = asMember($institution, 'Administrador')
        ->put(tenantUrl($institution, '/plantel'), institutionForm(['dea_code' => 'OD-SUR']))
        ->assertSessionHasErrors(['dea_code' => 'Ese código DEA ya está registrado.']);

    expect(json_encode(session('errors')->getMessages()))->not->toContain('Colegio Sur');
});

test('the form is checked at the edge too', function () {
    $institution = tenant();

    asMember($institution, 'Administrador')
        ->put(tenantUrl($institution, '/plantel'), institutionForm(['type' => 'militar', 'levels' => 'primaria', 'email' => 'x']))
        ->assertSessionHasErrors(['type', 'levels', 'email']);
});

test('the logo is served to the members of the institution, privately and as stored', function () {
    $institution = tenant();

    asMember($institution, 'Administrador')
        ->post(tenantUrl($institution, '/plantel/logo'), ['file' => uploadedImage(Images::png())])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $response = asMember($institution, 'Docente')->get(tenantUrl($institution, '/plantel/logo'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Content-Disposition'))->toBe('inline')
        ->and($response->getContent())->toBe(Images::png());
});

test('the logo URL is shared with the pages once there is a logo', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->get(tenantUrl($institution, '/plantel'))
        ->assertInertia(fn ($page) => $page
            ->where('institution.logoUrl', null)
            ->where('auth.can.viewInstitution', true)
            ->where('auth.can.manageInstitution', true));

    $this->actingAs($admin)->post(tenantUrl($institution, '/plantel/logo'), ['file' => uploadedImage(Images::png())]);

    $this->actingAs($admin)->get(tenantUrl($institution, '/plantel'))
        ->assertInertia(fn ($page) => $page->where('institution.logoUrl', fn (string $url) => str_contains($url, '/plantel/logo?v=')));
});

test('a file that only looks like an image is refused', function (string $contents, string $name) {
    $institution = tenant();

    asMember($institution, 'Administrador')
        ->post(tenantUrl($institution, '/plantel/logo'), ['file' => uploadedImage($contents, $name)])
        ->assertSessionHasErrors('file');

    expect(Storage::disk(config('tenancy.files_disk'))->allFiles())->toBe([]);
})->with([
    'php named png' => ['<?php system($_GET["c"]);', 'logo.png'],
    'png header only' => [Images::pngHeaderOnly(), 'logo.png'],
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg'],
]);

test('signatures are uploaded, served and removed only by the manager', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/plantel/firmas/director'), [
        'signer_name' => 'Ana Pérez', 'signer_title' => 'Directora', 'file' => uploadedImage(Images::jpeg(), 'firma.jpg'),
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->get(tenantUrl($institution, '/plantel/firmas/director'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    asMember($institution, 'Docente')->get(tenantUrl($institution, '/plantel/firmas/director'))->assertForbidden();

    $this->actingAs($admin)->delete(tenantUrl($institution, '/plantel/firmas/director'))->assertRedirect();

    $this->actingAs($admin)->get(tenantUrl($institution, '/plantel/firmas/director'))->assertNotFound();
    expect(DB::table('institution_signatures')->count())->toBe(0);
});

test('a signature needs who signs and their title', function () {
    $institution = tenant();

    asMember($institution, 'Administrador')
        ->post(tenantUrl($institution, '/plantel/firmas/secretary'), ['file' => uploadedImage(Images::png())])
        ->assertSessionHasErrors(['signer_name', 'signer_title']);
});

test('only the two signature roles exist', function () {
    $institution = tenant();

    asMember($institution, 'Administrador')->get(tenantUrl($institution, '/plantel/firmas/tesorero'))->assertNotFound();
});

test('the images are not public nor served to another institution', function () {
    $institution = tenant();
    asMember($institution, 'Administrador')->post(tenantUrl($institution, '/plantel/logo'), ['file' => uploadedImage(Images::png())]);
    $other = tenant();

    auth()->logout();
    $this->get(tenantUrl($institution, '/plantel/logo'))->assertRedirect();

    useTenant($other);
    asMember($other, 'Administrador')->get(tenantUrl($other, '/plantel/logo'))->assertNotFound();
});

test('the location selectors walk the catalog', function () {
    $institution = tenant();
    $state = DB::table('states')->insertGetId(['name' => 'Zulia', 'code' => 'H-ZU']);
    $municipality = DB::table('municipalities')->insertGetId(['state_id' => $state, 'name' => 'Maracaibo', 'code' => 'H-ZU-01']);
    $parish = DB::table('parishes')->insertGetId(['municipality_id' => $municipality, 'name' => 'Chiquinquirá', 'code' => 'H-ZU-01-01']);
    $member = asMember($institution, 'Administrador');

    $member->getJson(tenantUrl($institution, "/api/geografia/estados/{$state}/municipios"))
        ->assertOk()->assertExactJson(['data' => [['id' => $municipality, 'name' => 'Maracaibo']]]);
    $member->getJson(tenantUrl($institution, "/api/geografia/municipios/{$municipality}/parroquias"))
        ->assertOk()->assertExactJson(['data' => [['id' => $parish, 'name' => 'Chiquinquirá']]]);
});

test('the new name shows in the institution and in the central selector', function () {
    $institution = tenant(['name' => 'Colegio Viejo']);
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->put(tenantUrl($institution, '/plantel'), institutionForm(['name' => 'Colegio Nuevo']));

    $this->actingAs($admin)->get(tenantUrl($institution, '/plantel'))
        ->assertInertia(fn ($page) => $page->where('institution.name', 'Colegio Nuevo'));

    leaveTenant();
    $this->actingAs($admin)->get(centralUrl('/planteles'))
        ->assertInertia(fn ($page) => $page->where('institutions.0.name', 'Colegio Nuevo'));
});

test('a public institution saves and shows its dependency (SPEC-013 INS-R02.4)', function () {
    $institution = tenant();

    asMember($institution, 'Administrador')
        ->put(tenantUrl($institution, '/plantel'), institutionForm(['type' => 'publica', 'dependency' => 'estadal']))
        ->assertSessionHasNoErrors();

    expect($institution->fresh()->dependency)->toBe('estadal');

    asMember($institution, 'Administrador')->get(tenantUrl($institution, '/plantel'))
        ->assertInertia(fn ($page) => $page
            ->where('details.dependency', 'estadal')
            ->has('options.dependencies', 3)
        );
});

test('a dependency is refused for a private institution, or when unknown (SPEC-013 INS-R02.4, R02.5)', function () {
    $institution = tenant();

    asMember($institution, 'Administrador')
        ->put(tenantUrl($institution, '/plantel'), institutionForm(['type' => 'privada', 'dependency' => 'nacional']))
        ->assertSessionHasErrors(['dependency' => 'La dependencia solo aplica a planteles públicos.']);

    asMember($institution, 'Administrador')
        ->put(tenantUrl($institution, '/plantel'), institutionForm(['type' => 'publica', 'dependency' => 'federal']))
        ->assertSessionHasErrors('dependency');

    expect($institution->fresh()->dependency)->toBeNull();
});
