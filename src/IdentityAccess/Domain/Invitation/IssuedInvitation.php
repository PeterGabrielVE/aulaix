<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Invitation;

/**
 * An invitation together with the plain token of its link: the only moment
 * the token exists unhashed, to be emailed and then forgotten.
 */
final readonly class IssuedInvitation
{
    public function __construct(
        public Invitation $invitation,
        #[\SensitiveParameter] public string $token,
    ) {}
}
