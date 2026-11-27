<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use AulaX\Platform\Domain\CodeFailure;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;

interface OperatorMailer
{
    /**
     * The link to join the panel or enrol a TOTP again: the only place the
     * token ever leaves in clear (V4).
     */
    public function sendLink(OperatorEmail $email, string $name, #[\SensitiveParameter] string $token, InvitationPurpose $purpose): void;

    /**
     * Tells the blocked operator and every active SuperAdmin about a lockout
     * after 10 consecutive failed codes, including timestamps and IPs
     * (SPEC-017 RSP-R03.6).
     *
     * @param  list<CodeFailure>  $failures
     * @param  list<OperatorEmail>  $superAdmins
     */
    public function operatorBlocked(Operator $blocked, array $failures, array $superAdmins): void;
}
