<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Port\AccountMerger;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memberships are written only in their own institution (ADR 0006 C), so
 * each one moves with that institution active, inactive institutions
 * included: deleting the merged account would otherwise take its membership
 * there with it. Role assignments (model_has_roles, outside Row Level
 * Security) follow their membership. Leaves no institution active.
 */
final readonly class EloquentAccountMerger implements AccountMerger
{
    public function __construct(
        private TenantActivator $tenantActivator,
        private PermissionRegistrar $permissions,
    ) {}

    public function moveMemberships(AccountId $from, AccountId $into): void
    {
        $memberships = DB::table('institution_memberships as membership')
            ->join('institutions', 'institutions.id', '=', 'membership.institution_id')
            ->where('membership.user_id', $from->value)
            ->get(['institutions.id', 'institutions.name', 'institutions.subdomain']);

        foreach ($memberships as $institution) {
            $context = new TenantContext(InstitutionId::fromInt((int) $institution->id), $institution->name, $institution->subdomain);

            $this->tenantActivator->within($context, function () use ($institution, $from, $into) {
                $roles = DB::table(config('permission.table_names.model_has_roles'))
                    ->where('model_type', (new AccountModel)->getMorphClass())
                    ->where(config('permission.column_names.team_foreign_key'), $institution->id)
                    ->where('model_id', $from->value);

                // Already a member there (should not happen: one account per
                // institution before SPEC-004): the survivor's membership stays.
                if (MembershipModel::query()->where('user_id', $into->value)->exists()) {
                    MembershipModel::query()->where('user_id', $from->value)->delete();
                    $roles->delete();

                    return;
                }

                MembershipModel::query()->where('user_id', $from->value)->update(['user_id' => $into->value]);
                $roles->update(['model_id' => $into->value]);
            });
        }

        $this->permissions->forgetCachedPermissions();
    }
}
