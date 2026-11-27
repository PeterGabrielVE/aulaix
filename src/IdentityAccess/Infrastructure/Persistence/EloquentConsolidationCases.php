<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Consolidation\CaseAccount;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationCase;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationCases;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use Illuminate\Support\Facades\DB;

/**
 * Read with no institution active (console): every account and membership
 * is visible (ADR 0006 A, C).
 */
final class EloquentConsolidationCases implements ConsolidationCases
{
    public function underReview(): array
    {
        $accounts = AccountModel::query()
            ->where('consolidation_state', ConsolidationState::Review->value)
            ->orderBy('email')
            ->orderBy('id')
            ->get(['id', 'email', 'name']);

        $institutions = DB::table('institution_memberships as membership')
            ->join('institutions', 'institutions.id', '=', 'membership.institution_id')
            ->whereIn('membership.user_id', $accounts->pluck('id'))
            ->orderBy('institutions.name')
            ->get(['membership.user_id', 'institutions.name'])
            ->groupBy('user_id');

        return $accounts->groupBy('email')
            ->map(fn ($group, string $email) => new ConsolidationCase($email, $group->map(fn (AccountModel $account) => new CaseAccount(
                (int) $account->id,
                $account->name,
                ($institutions[$account->id] ?? collect())->pluck('name')->values()->all(),
            ))->values()->all()))
            ->values()
            ->all();
    }
}
