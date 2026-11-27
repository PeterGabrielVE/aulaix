<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\TenantActivator;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * The only place the platform activates an institution (ADR 0010, SPEC-014
 * V1): for the length of one read, under that institution's RLS, in a
 * read-only transaction that is always rolled back. The switch itself goes
 * through TenantActivator::within() (ADR 0011), which gives the previous
 * institution back; rolling back undoes the RLS setting too, and both are
 * checked afterwards, failing loudly if any trace is left.
 */
final readonly class TenantScopedReader
{
    private const string SETTING = 'app.current_institution_id';

    public function __construct(
        private TenantActivator $activator,
        private CurrentTenant $tenant,
        private InstitutionContexts $institutions,
        private ConnectionInterface $connection,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     *
     * @throws NotFound
     */
    public function read(int $institutionId, callable $read): mixed
    {
        $target = $this->institutions->of(InstitutionId::fromInt($institutionId))
            ?? throw new NotFound('Ese plantel no existe.');
        $previous = $this->tenant->context();
        $expectedSetting = $this->setting();

        $this->connection->beginTransaction();

        try {
            $this->connection->statement('SET TRANSACTION READ ONLY');

            return $this->activator->within($target, fn () => PlatformReadScope::within($institutionId, $read));
        } finally {
            $this->connection->rollBack();
            $this->ensureRestored($expectedSetting, $previous?->institutionId?->value);
        }
    }

    private function ensureRestored(string $expectedSetting, ?int $expectedTenant): void
    {
        if ($this->setting() !== $expectedSetting || $this->tenant->institutionId()?->value !== $expectedTenant) {
            throw new LogicException('A platform read left an institution active.');
        }
    }

    /**
     * The connection's RLS setting, empty when none.
     */
    private function setting(): string
    {
        return (string) $this->connection->scalar('select current_setting(?, true)', [self::SETTING]);
    }
}
