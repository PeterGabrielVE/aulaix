<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

/**
 * An email change together with the plain token of its verification link:
 * the only moment the token exists unhashed, to be emailed and forgotten.
 */
final readonly class IssuedEmailChange
{
    public function __construct(
        public EmailChangeRequest $request,
        #[\SensitiveParameter] public string $token,
    ) {}
}
