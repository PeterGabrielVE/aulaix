<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

/**
 * What a link is for: joining the panel (PLT-R03.1) or enrolling a TOTP
 * again after a reset (PLT-R03.7).
 */
enum InvitationPurpose: string
{
    case Onboarding = 'onboarding';
    case MfaReset = 'mfa_reset';
}
