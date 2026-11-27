<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Membership;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';
}
