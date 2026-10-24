<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\NotFound;

/**
 * The owner changes their name, for every institution (IDG-R05.1). Recorded
 * without the names themselves (IDG-R10.2).
 */
final readonly class ChangeNameHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $accountId, string $name): void
    {
        $account = $this->accounts->byId(AccountId::fromInt($accountId)) ?? throw new NotFound('La cuenta no existe.');
        $renamed = $account->changeName(PersonName::fromString($name));

        $this->transactions->run(function () use ($renamed) {
            $this->accounts->save($renamed);
            $this->audit->recordForPlatform(AuditEvent::of('identity.account.name_changed', AuditSubject::of('account', $renamed->id->value)));
        });
    }
}
