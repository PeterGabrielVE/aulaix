<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

interface OperatorRepository
{
    public function byId(OperatorId $id): ?Operator;

    public function byEmail(OperatorEmail $email): ?Operator;

    /**
     * Inserts a new operator or updates an existing one.
     */
    public function save(Operator $operator): OperatorId;

    public function recoveryCodes(OperatorId $id): RecoveryCodes;

    /**
     * Replaces the operator's recovery codes with these.
     */
    public function saveRecoveryCodes(OperatorId $id, RecoveryCodes $codes): void;
}
