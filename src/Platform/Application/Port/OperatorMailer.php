<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorEmail;

interface OperatorMailer
{
    /**
     * The link to join the panel or enrol a TOTP again: the only place the
     * token ever leaves in clear (V4).
     */
    public function sendLink(OperatorEmail $email, string $name, #[\SensitiveParameter] string $token, InvitationPurpose $purpose): void;
}
