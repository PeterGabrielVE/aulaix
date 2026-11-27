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
        private ConsolidationLock $locks,
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
            return $this->execute($command);
        } catch (CaseResolutionRefused $refused) {
            $this->recordRefusal($command, $refused);

            throw $refused;
        }
    }

    /**
     * @throws CaseResolutionRefused
     */
    private function execute(ResolveConsolidationCaseCommand $command): CaseResolution
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

        $requested = array_values(array_unique($command->accountIds));
        sort($requested);

        // Read outside the lock only to learn which email to lock (ADR 0014, rule 1).
        $first = count($requested) >= 2 ? $this->accounts->byId(AccountId::fromInt($requested[0])) : null;

        if ($first === null) {
            throw CaseResolutionRefused::notUnderReview();
        }

        $email = $first->email;
        $checkGroup = fn (array $group): array => $this->assertUnderReview($group, $requested);

        if ($command->dryRun) {
            return $this->transactions->run(function () use ($email, $checkGroup, $command, $requested) {
                $this->locks->lockEmail($email);
                $checkGroup($this->accounts->byEmail($email));

                // SPEC-017 RSP-R07.2, R07.3: audit the preview with ids and decision only (no PII).
                $this->audit->recordForPlatform(AuditEvent::of(
                    'identity.consolidation.previewed',
                    AuditSubject::of('account', $requested[0]),
                    [
                        FieldChange::of('decision', null, $command->decision->value),
                        FieldChange::of('accounts', null, $requested),
                    ],
                ));

                return $command->decision === ConsolidationDecision::Consolidate
                    ? new CaseResolution($command->decision, $requested[0], array_slice($requested, 1), false)
                    : new CaseResolution($command->decision, null, $requested, false);
            });
        }

        $context = [
            FieldChange::of('decision', null, $command->decision->value),
            FieldChange::of('reason', null, trim($command->reason)),
        ];

        if ($command->decision === ConsolidationDecision::Consolidate) {
            $survivor = $this->consolidator->consolidate(
                $email,
                $checkGroup,
                null,
                [...$context, FieldChange::of('verification_ref', null, $verificationRef)],
            );

            return new CaseResolution(
                $command->decision,
                $survivor->id->value,
                array_values(array_filter($requested, fn (int $id) => $id !== $survivor->id->value)),
                true,
            );
        }

        $this->transactions->run(function () use ($email, $checkGroup, $requested, $context) {
            $this->locks->lockEmail($email);
            $group = $checkGroup($this->accounts->byEmail($email));

            foreach ($group as $account) {
                $this->accounts->save($account->keptSeparate());
            }

            $this->audit->recordForPlatform(AuditEvent::of(
                'identity.account.kept_separate',
                AuditSubject::of('account', $requested[0]),
                [FieldChange::of('accounts', null, $requested), ...$context],
            ));
        });

        return new CaseResolution($command->decision, null, $requested, true);
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
     * Every account sharing the email, all still under review (IDG-R11.5, RSP-R04).
     *
     * @param  list<Account>  $group
     * @param  list<int>  $requested  Already unique and sorted.
     * @return list<Account>
     *
     * @throws CaseResolutionRefused
     */
    private function assertUnderReview(array $group, array $requested): array
    {
        $groupIds = array_map(fn (Account $account) => $account->id->value, $group);
        sort($groupIds);

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
