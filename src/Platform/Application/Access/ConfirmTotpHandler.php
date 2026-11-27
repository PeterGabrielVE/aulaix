<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * A fresh TOTP before a sensitive action (PLT-NF04). The code counts as
 * used, as at login (PLT-NF03).
 */
final readonly class ConfirmTotpHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private TotpCodes $totp,
        private Clock $clock,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(#[\SensitiveParameter] string $code): void
    {
        $operator = PlatformAccess::operator($this->session, $this->operators);
        $now = $this->clock->now();
        $step = $this->totp->verify((string) $operator->totpSecret, $code, $now)
            ?? throw BusinessRuleViolation::onField('code', 'El código no es correcto.');

        $operator->useTotpStep($step);
        $this->operators->save($operator);
        $this->session->confirmTotp($now);
    }
}
