<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Transfer;

/**
 * Where a transfer request stands. Expired is never stored: it is a pending
 * request past its 15 days (EST-R07.7).
 */
enum TransferStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
