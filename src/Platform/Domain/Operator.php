<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A person who operates the platform (SPEC-014), apart from every account
 * of the institutions (ADR 0010).
 *
 * Enters only once active and with a confirmed TOTP (PLT-R01.4, R01.6), and
 * never with a time step already used (PLT-NF03). Its session version goes
 * up whenever its open sessions must end: deactivation, a new role or a
 * reset of its second factor (PLT-R03.4, R03.7). Checking a TOTP code is the
 * application's job (TotpCodes); this aggregate keeps what was confirmed.
 */
final class Operator
{
    private function __construct(
        public readonly ?OperatorId $id,
        public readonly string $name,
        public readonly OperatorEmail $email,
        public private(set) OperatorRole $role,
        public private(set) OperatorStatus $status,
        public private(set) ?string $passwordHash,
        public private(set) ?string $totpSecret,
        public private(set) ?int $totpLastStep,
        public private(set) int $sessionVersion,
    ) {}

    public static function invite(string $name, OperatorEmail $email, OperatorRole $role): self
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw BusinessRuleViolation::onField('name', 'El nombre es obligatorio y admite como máximo 120 caracteres.');
        }

        return new self(null, $name, $email, $role, OperatorStatus::Pending, null, null, null, 1);
    }

    public static function reconstitute(
        OperatorId $id,
        string $name,
        OperatorEmail $email,
        OperatorRole $role,
        OperatorStatus $status,
        ?string $passwordHash,
        ?string $totpSecret,
        ?int $totpLastStep,
        int $sessionVersion,
    ): self {
        return new self($id, $name, $email, $role, $status, $passwordHash, $totpSecret, $totpLastStep, $sessionVersion);
    }

    public function canLogIn(): bool
    {
        return $this->status === OperatorStatus::Active && $this->passwordHash !== null && $this->totpSecret !== null;
    }

    /**
     * Accepting the invitation: its password and a TOTP confirmed by a
     * first code (PLT-R03.1).
     */
    public function activate(string $passwordHash, string $totpSecret, int $step): void
    {
        if ($this->status !== OperatorStatus::Pending) {
            throw BusinessRuleViolation::onField('operator', 'Este operador ya aceptó su invitación.');
        }

        $this->status = OperatorStatus::Active;
        $this->passwordHash = $passwordHash;
        $this->totpSecret = $totpSecret;
        $this->totpLastStep = $step;
    }

    /**
     * After a reset of its second factor (PLT-R03.7).
     */
    public function enrolTotp(string $totpSecret, int $step): void
    {
        if ($this->totpSecret !== null || $this->status === OperatorStatus::Pending) {
            throw BusinessRuleViolation::onField('operator', 'Este operador ya tiene un segundo factor.');
        }

        $this->totpSecret = $totpSecret;
        $this->totpLastStep = $step;
    }

    /**
     * PLT-NF03: a code is accepted once, and never one older than the last.
     */
    public function useTotpStep(int $step): void
    {
        if ($this->totpLastStep !== null && $step <= $this->totpLastStep) {
            throw BusinessRuleViolation::onField('code', 'Ese código ya se usó.');
        }

        $this->totpLastStep = $step;
    }

    public function deactivate(): void
    {
        $this->status = OperatorStatus::Inactive;
        $this->sessionVersion++;
    }

    public function reactivate(): void
    {
        $this->status = $this->passwordHash === null ? OperatorStatus::Pending : OperatorStatus::Active;
    }

    public function changeRole(OperatorRole $role): void
    {
        if ($role === $this->role) {
            return;
        }

        $this->role = $role;
        $this->sessionVersion++;
    }

    public function resetMfa(): void
    {
        $this->totpSecret = null;
        $this->totpLastStep = null;
        $this->sessionVersion++;
    }

    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }
}
