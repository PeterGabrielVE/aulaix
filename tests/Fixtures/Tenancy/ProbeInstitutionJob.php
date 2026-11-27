<?php

namespace Tests\Fixtures\Tenancy;

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\InstitutionJob;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use AulaX\Tenancy\Infrastructure\Queue\RunInInstitution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * A queued job that acts on one institution (SPEC-015 RLT-R03.2): records
 * what it saw, for the tests.
 */
final class ProbeInstitutionJob implements InstitutionJob, ShouldQueue
{
    use Dispatchable, Queueable;

    /** @var list<array{setting: string, tenant: ?int, years: int, openTransactions: int}> */
    public static array $runs = [];

    public function __construct(public readonly int $institution) {}

    public function institutionId(): int
    {
        return $this->institution;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [app(RunInInstitution::class)];
    }

    public function handle(CurrentTenant $tenant): void
    {
        self::$runs[] = [
            'setting' => RowLevelSecurity::currentSetting(),
            'tenant' => $tenant->institutionId()?->value,
            'years' => DB::table('school_years')->count(),
            'openTransactions' => DB::transactionLevel(),
        ];
    }
}
