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
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Members and invitations of the active institution, as Row Level Security
 * shows them: only accounts with a membership in it are visible (ADR 0006).
 */
final readonly class EloquentMemberDirectory implements MemberDirectory
{
    public function __construct(private RoleCatalog $roles) {}

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
            ->where('membership.status', '!=', MembershipStatus::Ended->value)
            ->select('users.id', 'users.name', 'users.email', 'membership.status');
    }

    /**
     * @return Builder<InvitationModel>
     */
    private function invitationQuery(): Builder
    {
        return InvitationModel::query()->where('status', InvitationStatus::Pending->value);
    }

    private function toView(object $row): MemberView
    {
        return new MemberView(
            (int) $row->id,
            $row->name,
            $row->email,
            $this->roles->rolesOf(AccountId::fromInt((int) $row->id))->inPriorityOrder(),
            $row->status === MembershipStatus::Active->value,
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
