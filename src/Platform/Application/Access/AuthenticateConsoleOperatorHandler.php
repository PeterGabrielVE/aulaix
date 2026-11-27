<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Authenticates a platform operator by TOTP before the console consolidation
 * command simulates or applies anything (SPEC-017 RSP-R06.1 – R06.7).
 *
 * Uses CodeAttempt so TOTP steps are single-use across panel and console
 * (RSP-R06.6) and failed codes count towards the 10-failure lockout
 * (RSP-R06.5). Recovery codes are never accepted in the console (RSP-R06.7).
 */
final readonly class AuthenticateConsoleOperatorHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private CodeAttempt $codeAttempt,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  ?string  $code  Null when the console has no interactive terminal (RSP-R06.4).
     *
     * @throws BusinessRuleViolation
     */
    public function __invoke(string $email, #[\SensitiveParameter] ?string $code): OperatorId
    {
        $named = trim($email);
        $this->auditContext->actAsConsoleOperator($named !== '' ? $named : 'desconocido');

        if ($code === null) {
            $this->recordRefusal('non_interactive');

            throw BusinessRuleViolation::onField('code', 'Este comando requiere una terminal interactiva para confirmar tu código TOTP.');
        }

        $operator = $this->operatorWith($named);

        if ($operator === null) {
            $this->recordRefusal('unknown_operator');

            throw BusinessRuleViolation::onField('operator', 'El operador no está autorizado para resolver consolidaciones.');
        }

        $result = $this->codeAttempt->attempt(
            $operator->id,
            $code,
            ip: 'console',
            allowRecoveryCode: false,
        );

        if (! $result->accepted) {
            $this->recordRefusal((string) $result->reason);

            throw BusinessRuleViolation::onField('code', 'El operador no pudo autenticarse con ese código TOTP.');
        }

        return $result->operator->id;
    }

    private function operatorWith(string $email): ?Operator
    {
        try {
            return $this->operators->byEmail(OperatorEmail::fromString($email));
        } catch (BusinessRuleViolation) {
            return null;
        }
    }

    private function recordRefusal(string $reason): void
    {
        $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::consoleAuthRefused($reason)));
    }
}
