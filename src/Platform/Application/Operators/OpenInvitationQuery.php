<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Platform\Application\Port\PendingTotpSecrets;
use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorInvitationRejected;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\Clock;

/**
 * Opening a link (PLT-R01.6, R03.2): if still usable, the TOTP secret to
 * enrol, kept in the session until confirmed. Expired, used, revoked and
 * unknown links all answer the same (V4).
 */
final readonly class OpenInvitationQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorInvitationRepository $invitations,
        private TotpCodes $totp,
        private PendingTotpSecrets $secrets,
        private Clock $clock,
    ) {}

    /**
     * @throws OperatorInvitationRejected
     */
    public function __invoke(#[\SensitiveParameter] string $token): EnrolmentView
    {
        $hash = hash('sha256', $token);
        $invitation = $this->invitations->byTokenHash($hash);
        $operator = $invitation === null ? null : $this->operators->byId($invitation->operatorId);

        if ($invitation === null || $operator === null || ! $invitation->isUsableAt($this->clock->now())) {
            throw OperatorInvitationRejected::create();
        }

        $secret = $this->secrets->get($hash);

        if ($secret === null) {
            $secret = $this->totp->newSecret();
            $this->secrets->put($hash, $secret);
        }

        return new EnrolmentView(
            $operator->name,
            $operator->email->value,
            $invitation->purpose === InvitationPurpose::Onboarding,
            $secret,
            $this->totp->qrSvg($operator->email->value, $secret),
        );
    }
}
