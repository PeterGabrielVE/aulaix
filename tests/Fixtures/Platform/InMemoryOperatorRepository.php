<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\RecoveryCodes;

/**
 * Operators and their recovery codes, in memory.
 */
final class InMemoryOperatorRepository implements OperatorRepository
{
    /** @var array<int, Operator> */
    public array $operators = [];

    /** @var array<int, RecoveryCodes> */
    public array $codes = [];

    public function byId(OperatorId $id): ?Operator
    {
        return isset($this->operators[$id->value]) ? clone $this->operators[$id->value] : null;
    }

    public function byEmail(OperatorEmail $email): ?Operator
    {
        foreach ($this->operators as $operator) {
            if ($operator->email->value === $email->value) {
                return clone $operator;
            }
        }

        return null;
    }

    public function save(Operator $operator): OperatorId
    {
        $id = $operator->id ?? OperatorId::fromInt(count($this->operators) + 1);

        $this->operators[$id->value] = Operator::reconstitute(
            $id, $operator->name, $operator->email, $operator->role, $operator->status,
            $operator->passwordHash, $operator->totpSecret, $operator->totpLastStep, $operator->sessionVersion,
        );

        return $id;
    }

    public function recoveryCodes(OperatorId $id): RecoveryCodes
    {
        return $this->codes[$id->value] ?? RecoveryCodes::reconstitute([]);
    }

    public function saveRecoveryCodes(OperatorId $id, RecoveryCodes $codes): void
    {
        $this->codes[$id->value] = $codes;
    }
}
