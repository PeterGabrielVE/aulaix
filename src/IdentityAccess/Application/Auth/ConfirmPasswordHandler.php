<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The logged-in person types their password again before a sensitive
 * operation (IDG-NF05). Checked against their own account, never by email:
 * an email awaiting consolidation has several accounts.
 */
final readonly class ConfirmPasswordHandler
{
    public function __construct(private Authenticator $authenticator) {}

    /**
     * @throws BusinessRuleViolation
     */
    public function __invoke(#[\SensitiveParameter] string $password): void
    {
        $account = $this->authenticator->current();

        if ($account === null || ! $this->authenticator->passwordMatches($account, $password)) {
            throw BusinessRuleViolation::onField('password', 'La contraseña es incorrecta.');
        }
    }
}
