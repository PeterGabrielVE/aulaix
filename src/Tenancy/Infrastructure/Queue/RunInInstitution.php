<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Queue;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\InstitutionJob;
use AulaX\Tenancy\Application\TenantActivator;
use Closure;
use LogicException;

/**
 * Job middleware (SPEC-015 RLT-R03.2): runs an InstitutionJob within() the
 * institution it carries, in a transaction of its own.
 */
final readonly class RunInInstitution
{
    public function __construct(
        private TenantActivator $activator,
        private InstitutionContexts $institutions,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        if (! $job instanceof InstitutionJob) {
            throw new LogicException($job::class.' does not say which institution it acts on.');
        }

        $institution = $this->institutions->of(InstitutionId::fromInt($job->institutionId()))
            ?? throw new LogicException("The institution {$job->institutionId()} of a queued job does not exist.");

        return $this->activator->within($institution, fn () => $next($job));
    }
}
