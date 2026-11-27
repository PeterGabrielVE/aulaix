<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Queue;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\InstitutionJob;
use AulaX\Tenancy\Application\InstitutionUnavailable;
use AulaX\Tenancy\Application\TenantActivator;
use Closure;
use LogicException;
use Psr\Log\LoggerInterface;

/**
 * Job middleware (SPEC-015 RLT-R03.2, SPEC-017 RSP-R08.5): runs an
 * InstitutionJob within() the institution it carries, in a transaction of
 * its own, taking the shared institution status lock and discarding the job
 * without retrying if the institution is inactive.
 */
final readonly class RunInInstitution
{
    public function __construct(
        private TenantActivator $activator,
        private InstitutionContexts $institutions,
        private LoggerInterface $log,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        if (! $job instanceof InstitutionJob) {
            throw new LogicException($job::class.' does not say which institution it acts on.');
        }

        $id = InstitutionId::fromInt($job->institutionId());
        $institution = $this->institutions->of($id)
            ?? throw new LogicException("The institution {$job->institutionId()} of a queued job does not exist.");

        return $this->activator->within($institution, function () use ($id, $job, $next) {
            try {
                $this->institutions->writableOf($id);
            } catch (InstitutionUnavailable) {
                $this->log->error('Discarding queued job for inactive institution.', [
                    'job' => $job::class,
                    'institution_id' => $id->value,
                ]);

                return null;
            }

            return $next($job);
        });
    }
}
