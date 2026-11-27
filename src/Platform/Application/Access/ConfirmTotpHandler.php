<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use DateTimeImmutable;

/**
 * A fresh TOTP before a sensitive action (PLT-NF04, SPEC-017 RSP-R01.4,
 * R03.2, R03.5 – R03.8). Locked and re-read via CodeAttempt so concurrent
 * management or replayed codes cannot slip through.
 */
final readonly class ConfirmTotpHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private CodeAttempt $codeAttempt,
        private Clock $clock,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(#[\SensitiveParameter] string $code, string $ip = ''): void
    {
        $operator = PlatformAccess::operator($this->session, $this->operators, $this->clock->now());

        $result = $this->codeAttempt->attempt(
            $operator->id,
            $code,
            $ip,
            allowRecoveryCode: false,
            onSuccess: fn (Operator $fresh, ?int $left, DateTimeImmutable $at) => $this->session->confirmTotp($at),
        );

        if ($result->accepted) {
            return;
        }

        if ($result->blockedNow || in_array($result->reason, ['not_active', 'blocked'], true)) {
            $this->session->end();
        }

        if (in_array($result->reason, ['not_active', 'blocked'], true)) {
            throw new AuthorizationDenied('Necesitas una sesión de operador.');
        }

        throw BusinessRuleViolation::onField('code', 'El código no es correcto.');
    }
}
