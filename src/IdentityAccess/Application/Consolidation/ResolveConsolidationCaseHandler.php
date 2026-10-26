<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

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
 * decision and the reason. The console command and the platform panel are
 * both inputs to this use case (IDG-NF07); each names its actor in the
 * AuditContext before calling it (console operator or panel operator).
 */
final readonly class ResolveConsolidationCaseHandler
{
    public function __construct(
        private ConsolidationOperators $operators,
        private AccountRepository $accounts,
        private AccountConsolidator $consolidator,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws CaseResolutionRefused
     */
    public function __invoke(ResolveConsolidationCaseCommand $command): CaseResolution
    {
        try {
            $group = $this->checked($command);
        } catch (CaseResolutionRefused $refused) {
            $this->recordRefusal($command, $refused);

            throw $refused;
        }

        $verificationRef = trim((string) $command->verificationRef);
        $ids = array_map(fn (Account $account) => $account->id->value, $group);
        sort($ids);

        $resolution = $command->decision === ConsolidationDecision::Consolidate
            ? new CaseResolution($command->decision, $ids[0], array_slice($ids, 1), ! $command->dryRun)
            : new CaseResolution($command->decision, null, $ids, ! $command->dryRun);

        if ($command->dryRun) {
            return $resolution;
        }

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
     * The operator, the reason, the proof of ownership and the case itself.
     *
     * @return list<Account>
     *
     * @throws CaseResolutionRefused
     */
    private function checked(ResolveConsolidationCaseCommand $command): array
    {
        if (trim($command->operator) === '' || ! $this->operators->isAuthorized($command->operator)) {
            throw CaseResolutionRefused::operatorNotAuthorized();
        }

        if (trim($command->reason) === '') {
            throw CaseResolutionRefused::missing('el motivo');
        }

        if ($command->decision === ConsolidationDecision::Consolidate && trim((string) $command->verificationRef) === '') {
            throw CaseResolutionRefused::missing('la referencia de la verificación de titularidad');
        }

        return $this->caseOf($command->accountIds);
    }

    /**
     * Every refusal goes to the platform log, dry run or not (SPEC-014 V3):
     * who tried, on which accounts and why it was refused.
     */
    private function recordRefusal(ResolveConsolidationCaseCommand $command, CaseResolutionRefused $refused): void
    {
        $requested = array_values(array_unique($command->accountIds));
        sort($requested);

        $this->transactions->run(fn () => $this->audit->recordForPlatform(AuditEvent::of(
            'identity.consolidation.refused',
            AuditSubject::of('account', $requested[0] ?? 'ninguna'),
            [
                FieldChange::of('accounts', null, $requested),
                FieldChange::of('decision', null, $command->decision->value),
                FieldChange::of('refusal', null, $refused->getMessage()),
            ],
        )));
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
