<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Invitation;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
}
