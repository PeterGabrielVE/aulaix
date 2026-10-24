<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Tenancy\Application\CurrentTenant;
use LogicException;

/**
 * The transfers screen (EST-R07.4, R07.6, R07.7): the requests to answer,
 * with the destination and the student asked for, and the open
 * institution's own requests with their answer or expiry.
 */
final readonly class ListTransfersQuery
{
    public function __construct(
        private TransferList $transfers,
        private CurrentTenant $tenant,
        private StudentPermissions $permissions,
        private Clock $clock,
    ) {}

    public function __invoke(): TransferBoard
    {
        StudentAccess::ensureCanManage($this->permissions);
        $institution = $this->tenant->institutionId() ?? throw new LogicException('Transfers are listed for an open institution.');

        return $this->transfers->boardOf($institution, $this->clock->now());
    }
}
