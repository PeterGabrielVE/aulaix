<?php

use App\Http\Navigation\MenuCatalog;
use AulaX\Workspace\Domain\MenuEntry;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-013 NAV-R03.3, R03.5: an entry shows exactly when its screen lets
 * the person in. The menu is not the protection: every hidden screen still
 * refuses by itself.
 */
function menuKeysOf(object $user, object $institution): array
{
    $keys = [];

    test()->actingAs($user)->get(tenantUrl($institution, '/profile'))
        ->assertInertia(function ($page) use (&$keys) {
            foreach ($page->toArray()['props']['layout']['navigation'] as $group) {
                array_push($keys, ...array_column($group['entries'], 'key'));
            }
        });

    return $keys;
}

function linkedGuardian(object $institution, object $user): void
{
    useTenant($institution);
    DB::table('guardians')->insert([
        'institution_id' => $institution->id, 'given_names' => 'María', 'family_names' => 'Mora',
        'document_type' => 'national_id', 'document' => 'V15432100', 'phone' => '0414', 'email' => $user->email,
        'account_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('every entry has its presentation, and only those', function () {
    expect(MenuCatalog::keys())->toBe(array_map(fn (MenuEntry $entry) => $entry->value, MenuEntry::cases()));
});

test('an entry shows exactly when its screen opens', function (string $role, bool $guardian) {
    $institution = tenant();
    $user = userWithRole($institution, $role);

    if ($guardian) {
        linkedGuardian($institution, $user);
    }

    $visible = menuKeysOf($user, $institution);

    foreach (MenuCatalog::keys() as $key) {
        $status = $this->actingAs($user)->get(route(MenuCatalog::routeOf($key)))->status();
        $opens = $status === 200 || ($key === 'home' && $status === 302);

        expect($opens)->toBe(in_array($key, $visible, true), "{$role}: «{$key}» answers {$status}");
    }
})->with([
    'Administrador' => ['Administrador', false],
    'Director' => ['Director', false],
    'Coordinador' => ['Coordinador', false],
    'Docente' => ['Docente', false],
    'Representante' => ['Representante', true],
    'Estudiante' => ['Estudiante', false],
    'Docente that is a guardian' => ['Docente', true],
]);
