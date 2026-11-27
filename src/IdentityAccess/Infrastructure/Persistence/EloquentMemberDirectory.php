<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Members\InvitationView;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\IdentityAccess\Application\Members\MemberPage;
use AulaX\IdentityAccess\Application\Members\MemberView;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Invitation\InvitationStatus;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Members and invitations of the active institution, as Row Level Security
 * shows them: only accounts with a membership in it are visible (ADR 0006).
 */
final readonly class EloquentMemberDirectory implements MemberDirectory
{
    public function __construct(
        private RoleCatalog $roles,
        private CurrentTenant $tenant,
    ) {}

    public function members(?string $search, int $page, int $perPage): MemberPage
    {
        $query = $this->memberQuery()
            ->when($search !== null, fn (QueryBuilder $query) => $query->where(fn (QueryBuilder $query) => $query
                ->where('users.name', 'ilike', '%'.self::escapeLike($search).'%')
                ->orWhere('users.email', 'ilike', '%'.self::escapeLike($search).'%')
            ));

        $total = (clone $query)->count();

        $rows = $query->orderBy('users.name')->orderBy('users.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new MemberPage(
            $rows->map(fn (object $row) => $this->toView($row))->values()->all(),
            $total,
            $page,
            $perPage,
        );
    }

    public function member(int $accountId): ?MemberView
    {
        $row = $this->memberQuery()->where('users.id', $accountId)->first();

        return $row === null ? null : $this->toView($row);
    }

    public function activeMemberByEmail(string $email): ?int
    {
        $id = $this->memberQuery()->where('users.email', mb_strtolower(trim($email)))->value('users.id');

        return $id === null ? null : (int) $id;
    }

    public function activeMembersWithRole(string $role): array
    {
        $teamKey = config('permission.column_names.team_foreign_key');

        return $this->memberQuery()
            ->where('membership.status', MembershipStatus::Active->value)
            ->whereExists(fn (QueryBuilder $assigned) => $assigned
                ->selectRaw('1')
                ->from(config('permission.table_names.model_has_roles').' as assigned')
                ->join('roles', 'roles.id', '=', 'assigned.role_id')
                ->whereColumn('assigned.model_id', 'users.id')
                ->where('assigned.model_type', (new AccountModel)->getMorphClass())
                ->where('assigned.'.$teamKey, $this->institutionId())
                ->where('roles.name', $role))
            ->orderByRaw('users.name collate "es-x-icu"')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name'])
            ->map(fn (object $row) => ['id' => (int) $row->id, 'name' => $row->name])
            ->all();
    }

    public function accountNames(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return DB::table('users')
            ->join('institution_memberships as membership', 'membership.user_id', '=', 'users.id')
            ->where('membership.institution_id', $this->institutionId())
            ->whereIn('users.id', $accountIds)
            ->get(['users.id', 'users.name', 'membership.status'])
            ->mapWithKeys(fn (object $row) => [(int) $row->id => ['name' => $row->name, 'active' => $row->status === MembershipStatus::Active->value]])
            ->all();
    }

    public function pendingInvitations(?string $search): array
    {
        return $this->invitationQuery()
            ->when($search !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'ilike', '%'.self::escapeLike($search).'%')
                ->orWhere('email', 'ilike', '%'.self::escapeLike($search).'%')
            ))
            ->latest('id')
            ->get()
            ->map(fn (InvitationModel $model) => self::toInvitationView($model))
            ->values()
            ->all();
    }

    public function pendingInvitation(int $invitationId): ?InvitationView
    {
        $model = $this->invitationQuery()->find($invitationId);

        return $model === null ? null : self::toInvitationView($model);
    }

    private function memberQuery(): QueryBuilder
    {
        return DB::table('users')
            ->join('institution_memberships as membership', 'membership.user_id', '=', 'users.id')
            ->where('membership.institution_id', $this->institutionId())
            ->where('membership.status', '!=', MembershipStatus::Ended->value)
            ->select('users.id', 'users.name', 'users.email', 'membership.status');
    }

    /**
     * @return Builder<InvitationModel>
     */
    private function invitationQuery(): Builder
    {
        return InvitationModel::query()
            ->where('institution_id', $this->institutionId())
            ->where('status', InvitationStatus::Pending->value);
    }

    private function institutionId(): int
    {
        return $this->tenant->institutionId()?->value
            ?? throw new LogicException('The member directory belongs to an institution: activate one first.');
    }

    private function toView(object $row): MemberView
    {
        return new MemberView(
            (int) $row->id,
            $row->name,
            $row->email,
            $this->roles->rolesOf(AccountId::fromInt((int) $row->id))->inPriorityOrder(),
            $row->status === MembershipStatus::Active->value,
            $this->roles->hasDirectPermission(AccountId::fromInt((int) $row->id), Permission::EditClosedTermGrades),
            $this->roles->hasDirectPermission(AccountId::fromInt((int) $row->id), Permission::ManageEnrollments),
        );
    }

    private static function toInvitationView(InvitationModel $model): InvitationView
    {
        return new InvitationView(
            (int) $model->id,
            $model->name,
            $model->email,
            RoleSet::of(...$model->roles)->inPriorityOrder(),
            $model->expires_at,
        );
    }

    /**
     * PostgreSQL's LIKE escapes with a backslash by default, so escaping the
     * backslash itself first makes %, _ and \ match literally.
     */
    private static function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }
}
