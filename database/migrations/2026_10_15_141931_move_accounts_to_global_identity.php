<?php

use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-004 (ADR 0004, ADR 0006): per-institution users become global
 * accounts with memberships.
 *
 *  - Each accepted user keeps their row (and password) and gets a membership
 *    in their institution; an inactive one, a membership suspended by it.
 *  - A user who never accepted their invitation becomes a pending invitation
 *    that keeps the bcrypt hash and the expiry of the emailed link.
 *  - Accounts sharing an email stay separate rows, marked "pending" (same
 *    name) or "review" (different names): nothing is merged (IDG-R09.2).
 *  - users loses institution_id and status; its email is unique among
 *    regular accounts only, and its RLS depends on the active institution.
 *
 * Passwords are never read into PHP, copied or moved (IDG-R09.7). The data
 * is read institution by institution, since users is still RLS-scoped.
 */
return new class extends Migration
{
    private const ACCOUNT_POLICIES = ['accounts_select', 'accounts_update', 'accounts_delete', 'accounts_insert'];

    public function up(): void
    {
        $now = now();
        $accounts = [];

        foreach (DB::table('institutions')->orderBy('id')->pluck('id') as $institutionId) {
            RowLevelSecurity::setInstitution($institutionId);

            foreach (DB::table('users')->orderBy('id')->get(['id', 'email', 'name', 'status', 'email_verified_at', 'created_at']) as $user) {
                $email = mb_strtolower(trim($user->email));

                if ($user->email_verified_at === null) {
                    $this->toInvitation($user, $institutionId, $email, $now);

                    continue;
                }

                $active = $user->status === 'active';
                DB::table('institution_memberships')->insert([
                    'institution_id' => $institutionId,
                    'user_id' => $user->id,
                    'status' => $active ? 'active' : 'suspended',
                    'suspension_reason' => $active ? null : 'institution',
                    'created_at' => $user->created_at,
                    'updated_at' => $now,
                ]);

                if ($email !== $user->email) {
                    DB::table('users')->where('id', $user->id)->update(['email' => $email]);
                }

                $accounts[] = ['id' => $user->id, 'email' => $email, 'name' => $user->name, 'institution' => $institutionId];
            }
        }

        $this->markDuplicates($accounts);

        RowLevelSecurity::clearInstitution();

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON users');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['institution_id', 'email']);
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn('status');
        });

        DB::statement('CREATE UNIQUE INDEX users_email_regular_unique ON users (email) WHERE consolidation_state IS NULL');

        $this->createAccountPolicies();
    }

    /**
     * Valid until the first consolidation merges accounts: from then on an
     * account may belong to several institutions and the old model can't
     * represent it, so go back with a backup instead.
     */
    public function down(): void
    {
        $memberships = $this->membershipsByInstitution();
        $userIds = array_merge(...array_values($memberships) ?: [[]]);

        if (count($userIds) !== count(array_unique(array_column($userIds, 'user_id')))) {
            throw new RuntimeException('Some account belongs to several institutions (already consolidated): restore a backup instead.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
        });

        // Filled while the account policies still let each institution see its members.
        foreach ($memberships as $institutionId => $rows) {
            RowLevelSecurity::setInstitution($institutionId);

            foreach ($rows as $row) {
                DB::table('users')->where('id', $row['user_id'])->update([
                    'institution_id' => $institutionId,
                    'status' => $row['status'] === 'active' ? 'active' : 'inactive',
                ]);
            }
        }

        RowLevelSecurity::clearInstitution();

        foreach (self::ACCOUNT_POLICIES as $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON users");
        }

        DB::statement('DROP INDEX IF EXISTS users_email_regular_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['institution_id', 'email']);
        });

        $current = RowLevelSecurity::CURRENT_INSTITUTION_SQL;
        DB::statement("CREATE POLICY tenant_isolation ON users USING (institution_id = {$current}) WITH CHECK (institution_id = {$current})");

        foreach (array_keys($memberships) as $institutionId) {
            RowLevelSecurity::setInstitution($institutionId);
            DB::table('institution_memberships')->delete();
        }

        $this->pendingInvitationsBackToUsers();

        RowLevelSecurity::clearInstitution();
    }

    /**
     * A user who never accepted their invitation. The broker's token row
     * (bcrypt hash) keeps the emailed link working until it expires; without
     * one, the invitation is migrated already expired, ready to be resent.
     */
    private function toInvitation(object $user, int $institutionId, string $email, Carbon $now): void
    {
        $token = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        DB::table('invitations')->insert([
            'institution_id' => $institutionId,
            'email' => $email,
            'name' => $user->name,
            'roles' => json_encode($this->rolesOf($user->id, $institutionId)),
            'token_hash' => $token?->token ?? hash('sha256', bin2hex(random_bytes(32))),
            'expires_at' => $token ? Carbon::parse($token->created_at)->addDays(7) : $now,
            'status' => 'pending',
            'created_at' => $user->created_at,
            'updated_at' => $now,
        ]);

        DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', 'App\Models\User')->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('users')->where('id', $user->id)->delete();
    }

    /**
     * @return list<string>
     */
    private function rolesOf(int $userId, int $institutionId): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->where('model_has_roles.institution_id', $institutionId)
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->all();
    }

    /**
     * @param  list<array{id: int, email: string, name: string, institution: int}>  $accounts
     */
    private function markDuplicates(array $accounts): void
    {
        $groups = collect($accounts)->groupBy('email')->filter(fn ($group) => $group->count() > 1);

        foreach ($groups as $group) {
            $first = PersonName::fromString($group->first()['name']);
            $samePerson = $group->every(fn (array $account) => PersonName::fromString($account['name'])->sameAs($first));

            foreach ($group as $account) {
                RowLevelSecurity::setInstitution($account['institution']);
                DB::table('users')->where('id', $account['id'])->update(['consolidation_state' => $samePerson ? 'pending' : 'review']);
            }
        }
    }

    /**
     * ADR 0006 A: with an institution active only its members are visible;
     * with none active, every account. Creating an account is always allowed:
     * it reveals nobody's data.
     */
    private function createAccountPolicies(): void
    {
        $current = RowLevelSecurity::CURRENT_INSTITUTION_SQL;
        $visible = "({$current} IS NULL OR EXISTS (
            SELECT 1 FROM institution_memberships m WHERE m.user_id = users.id AND m.institution_id = {$current}
        ))";

        DB::statement("CREATE POLICY accounts_select ON users FOR SELECT USING {$visible}");
        DB::statement("CREATE POLICY accounts_update ON users FOR UPDATE USING {$visible} WITH CHECK {$visible}");
        DB::statement("CREATE POLICY accounts_delete ON users FOR DELETE USING {$visible}");
        DB::statement('CREATE POLICY accounts_insert ON users FOR INSERT WITH CHECK (true)');
    }

    /**
     * @return array<int, list<array{user_id: int, status: string}>>
     */
    private function membershipsByInstitution(): array
    {
        $memberships = [];

        foreach (DB::table('institutions')->orderBy('id')->pluck('id') as $institutionId) {
            RowLevelSecurity::setInstitution($institutionId);
            $memberships[$institutionId] = DB::table('institution_memberships')->get(['user_id', 'status'])
                ->map(fn (object $row) => ['user_id' => $row->user_id, 'status' => $row->status])
                ->all();
        }

        RowLevelSecurity::clearInstitution();

        return array_filter($memberships);
    }

    /**
     * Only invitations migrated from the old model (bcrypt token) can become
     * a user again with a working link; others become users without one.
     */
    private function pendingInvitationsBackToUsers(): void
    {
        foreach (DB::table('institutions')->orderBy('id')->pluck('id') as $institutionId) {
            RowLevelSecurity::setInstitution($institutionId);

            foreach (DB::table('invitations')->where('status', 'pending')->get() as $invitation) {
                $userId = DB::table('users')->insertGetId([
                    'institution_id' => $institutionId,
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                    'status' => 'active',
                    'email_verified_at' => null,
                    'created_at' => $invitation->created_at,
                    'updated_at' => now(),
                ]);

                foreach (json_decode($invitation->roles, true) as $role) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => DB::table('roles')->where('institution_id', $institutionId)->where('name', $role)->value('id'),
                        'model_type' => 'App\Models\User',
                        'model_id' => $userId,
                        'institution_id' => $institutionId,
                    ]);
                }

                if (str_starts_with($invitation->token_hash, '$2y$')) {
                    DB::table('password_reset_tokens')->insert([
                        'email' => $invitation->email,
                        'token' => $invitation->token_hash,
                        'created_at' => Carbon::parse($invitation->expires_at)->subDays(7),
                    ]);
                }
            }

            DB::table('invitations')->delete();
        }
    }
};
