<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Audit\Application\AuditSubject;
use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRole;

/**
 * What the audit log keeps of a signature (INS-R06.2): its role and who
 * signs, never the image nor its path.
 */
final class SignatureAudit
{
    public static function subject(SignatureRole $role): AuditSubject
    {
        return AuditSubject::of('signature', $role->value);
    }

    /**
     * @return array<string, ?string>
     */
    public static function signer(?Signature $signature): array
    {
        return [
            'signer_name' => $signature?->signerName,
            'signer_title' => $signature?->signerTitle,
        ];
    }
}
