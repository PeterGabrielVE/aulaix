<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\Shared\Application\TransactionManager;

/**
 * Support resolves a case marked for review (IDG-R11.2 – R11.7): consolidate
 * the accounts, with proof that one person owns them, or keep them separate
 * for good. Only authorized operators, only the whole case and only while it
 * is still under review; all or nothing, recorded with the operator, the
 * decision and the reason. The console command and the future super
 * administration panel are both inputs to this use case (IDG-NF07).
 */
final readonly class ResolveConsolidationCaseHandler
{
    public function __construct(
        private ConsolidationOperators $operators,
        private AccountRepository $accounts,
        private AccountConsolidator $consolidator,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws CaseResolutionRefused
     */
    public function __invoke(ResolveConsolidationCaseCommand $command): CaseResolution
    {
        if (trim($command->operator) === '' || ! $this->operators->isAuthorized($command->operator)) {
            throw CaseResolutionRefused::operatorNotAuthorized();
        }

        if (trim($command->reason) === '') {
            throw CaseResolutionRefused::missing('el motivo');
        }

        $verificationRef = trim((string) $command->verificationRef);

        if ($command->decision === ConsolidationDecision::Consolidate && $verificationRef === '') {
            throw CaseResolutionRefused::missing('la referencia de la verificación de titularidad');
        }

        $group = $this->caseOf($command->accountIds);
        $ids = array_map(fn (Account $account) => $account->id->value, $group);
        sort($ids);

        $resolution = $command->decision === ConsolidationDecision::Consolidate
            ? new CaseResolution($command->decision, $ids[0], array_slice($ids, 1), ! $command->dryRun)
            : new CaseResolution($command->decision, null, $ids, ! $command->dryRun);

        if ($command->dryRun) {
            return $resolution;
        }

        $this->auditContext->actAsConsoleOperator($command->operator);

        $context = [
            FieldChange::of('decision', null, $command->decision->value),
            FieldChange::of('reason', null, trim($command->reason)),
        ];

        if ($command->decision === ConsolidationDecision::Consolidate) {
            $this->consolidator->consolidate($group, null, [...$context, FieldChange::of('verification_ref', null, $verificationRef)]);

            return $resolution;
        }

        $this->transactions->run(function () use ($group, $ids, $context) {
            foreach ($group as $account) {
                $this->accounts->save($account->keptSeparate());
            }

            $this->audit->recordForPlatform(AuditEvent::of(
                'identity.account.kept_separate',
                AuditSubject::of('account', $ids[0]),
                [FieldChange::of('accounts', null, $ids), ...$context],
            ));
        });

        return $resolution;
    }

    /**
     * Every account sharing the email, all still under review (IDG-R11.5).
     *
     * @param  list<int>  $accountIds
     * @return list<Account>
     *
     * @throws CaseResolutionRefused
     */
    private function caseOf(array $accountIds): array
    {
        $requested = array_values(array_unique($accountIds));
        $first = count($requested) >= 2 ? $this->accounts->byId(AccountId::fromInt($requested[0])) : null;

        if ($first === null) {
            throw CaseResolutionRefused::notUnderReview();
        }

        $group = $this->accounts->byEmail($first->email);
        $groupIds = array_map(fn (Account $account) => $account->id->value, $group);
        sort($groupIds);
        sort($requested);

        if ($groupIds !== $requested) {
            throw CaseResolutionRefused::notUnderReview();
        }

        foreach ($group as $account) {
            if ($account->consolidation !== ConsolidationState::Review) {
                throw CaseResolutionRefused::notUnderReview();
            }
        }

        return $group;
    }
}
