<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Files;

use AulaX\Tenancy\Domain\SignatureRole;

/**
 * The images an institution keeps: its logo and one signature per role.
 */
enum AssetKind: string
{
    case Logo = 'logo';
    case DirectorSignature = 'director-signature';
    case SecretarySignature = 'secretary-signature';

    public static function signatureOf(SignatureRole $role): self
    {
        return match ($role) {
            SignatureRole::Director => self::DirectorSignature,
            SignatureRole::Secretary => self::SecretarySignature,
        };
    }

    /**
     * The role whose signature this is, or null for the logo.
     */
    public function signatureRole(): ?SignatureRole
    {
        return match ($this) {
            self::Logo => null,
            self::DirectorSignature => SignatureRole::Director,
            self::SecretarySignature => SignatureRole::Secretary,
        };
    }
}
