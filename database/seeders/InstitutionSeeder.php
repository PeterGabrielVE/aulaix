<?php

namespace Database\Seeders;

use App\Enums\InstitutionStatus;
use App\Enums\UserStatus;
use App\Models\Institution;
use App\Models\User;
use App\Support\RowLevelSecurity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds two demo institutions with an admin user each, deliberately two
 * (not one) so tenant isolation (F1-01 → F1-03) and Row Level Security
 * (F1-02) can be exercised and demonstrated end-to-end: log into one
 * subdomain and confirm the other institution's data is unreachable.
 */
class InstitutionSeeder extends Seeder
{
    private const INSTITUTIONS = [
        [
            'name' => 'Colegio Demo Uno',
            'subdomain' => 'demo',
            'dea_code' => 'OD00010101',
            'rif' => 'J-00000001-0',
            'address' => 'Av. Principal, Caracas',
            'phone' => '0212-5550001',
            'email' => 'contacto@demo.aulaix.test',
        ],
        [
            'name' => 'Colegio Demo Dos',
            'subdomain' => 'demo2',
            'dea_code' => 'OD00010202',
            'rif' => 'J-00000002-0',
            'address' => 'Calle 2, Maracaibo',
            'phone' => '0261-5550002',
            'email' => 'contacto@demo2.aulaix.test',
        ],
    ];

    /**
     * One login per role in every demo institution, e.g.
     * docente@demo.aulaix.test / password.
     */
    private const DEMO_USERS = [
        'admin' => 'Administrador',
        'director' => 'Director',
        'coordinador' => 'Coordinador',
        'docente' => 'Docente',
        'representante' => 'Representante',
        'estudiante' => 'Estudiante',
    ];

    public function run(): void
    {
        foreach (self::INSTITUTIONS as $data) {
            $institution = Institution::query()->updateOrCreate(
                ['subdomain' => $data['subdomain']],
                [...$data, 'status' => InstitutionStatus::Active],
            );

            // Tenant-scoped tables enforce RLS (F1-02) even for this seeder's
            // own DB role, so writes to `users` need the same session
            // variable ResolveTenant sets on every real request.
            RowLevelSecurity::setInstitution($institution->id);

            RolePermissionSeeder::seedForInstitution($institution);

            foreach (self::DEMO_USERS as $prefix => $role) {
                $user = User::query()->updateOrCreate(
                    ['institution_id' => $institution->id, 'email' => "{$prefix}@{$data['subdomain']}.aulaix.test"],
                    [
                        'name' => $role,
                        'password' => Hash::make('password'),
                        'status' => UserStatus::Active,
                        'email_verified_at' => now(),
                    ],
                );

                $user->syncRoles([$role]);
            }
        }
    }
}
