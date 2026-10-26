<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * For IdentityAccess (SPEC-014 PLT-R06.6, V3): whether an email belongs to
 * an active operator of the panel, the only ones who resolve consolidation
 * cases from the console.
 */
final readonly class ActiveOperatorQuery
{
    public function __construct(private OperatorRepository $operators) {}

    public function __invoke(string $email): bool
    {
        try {
            $operator = $this->operators->byEmail(OperatorEmail::fromString($email));
        } catch (BusinessRuleViolation) {
            return false;
        }

        return $operator !== null && $operator->canLogIn();
    }
}
