<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The platform's operators, for a SuperAdmin (PLT-R03): who they are, their
 * role and state, and whether they have a second factor. Never a secret.
 */
final readonly class ListOperatorsQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
    ) {}

    /**
     * @return list<array{id: int, name: string, email: string, role: string, status: string, hasTotp: bool}>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        PlatformAccess::superAdmin($this->session, $this->operators);

        return array_map(fn (Operator $operator) => [
            'id' => $operator->id->value,
            'name' => $operator->name,
            'email' => $operator->email->value,
            'role' => $operator->role->value,
            'status' => $operator->status->value,
            'hasTotp' => $operator->totpSecret !== null,
        ], $this->operators->all());
    }
}
