<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * The roles of spatie/laravel-permission, whose teams are the institutions
 * (F1-06). Reads go straight to the tables, so they never see a role list
 * Spatie cached on a model under another institution.
 */
final readonly class SpatieRoleCatalog implements RoleCatalog
{
    public function __construct(private CurrentTenant $tenant) {}

    public function available(): RoleSet
    {
        return RoleSet::of(...$this->roles()->pluck('name')->all());
    }

    public function withPermission(Permission $permission): RoleSet
    {
        return RoleSet::of(...$this->roles()
            ->whereRelation('permissions', 'name', $permission->value)
            ->pluck('name')
            ->all());
    }

    public function rolesOf(AccountId $account): RoleSet
    {
        return RoleSet::of(...$this->assignments()
            ->where('assigned.model_id', $account->value)
            ->pluck('roles.name')
            ->all());
    }

    public function assign(AccountId $account, RoleSet $roles): void
    {
        AccountModel::query()->findOrFail($account->value)->syncRoles($roles->names());
    }

    public function activeHoldersOf(BaseRole $role): int
    {
        return $this->assignments()
            ->join('institution_memberships as membership', function ($join) {
                $join->on('membership.user_id', '=', 'assigned.model_id')
                    ->on('membership.institution_id', '=', 'assigned.'.self::teamKey());
            })
            ->where('roles.name', $role->value)
            ->where('membership.status', 'active')
            ->distinct()
            ->count('assigned.model_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Role>
     */
    private function roles(): \Illuminate\Database\Eloquent\Builder
    {
        return Role::query()->where(self::teamKey(), $this->institutionId());
    }

    private function assignments(): Builder
    {
        return DB::table(config('permission.table_names.model_has_roles').' as assigned')
            ->join('roles', 'roles.id', '=', 'assigned.role_id')
            ->where('assigned.model_type', (new AccountModel)->getMorphClass())
            ->where('assigned.'.self::teamKey(), $this->institutionId());
    }

    private function institutionId(): int
    {
        return $this->tenant->institutionId()?->value
            ?? throw new LogicException('Roles belong to an institution: activate one first.');
    }

    private static function teamKey(): string
    {
        return config('permission.column_names.team_foreign_key');
    }
}
