<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Domain\CodeFailure;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;

/**
 * The links and lockout notices sent, so tests can inspect them.
 */
final class RecordingOperatorMailer implements OperatorMailer
{
    /** @var list<array{email: string, name: string, token: string, purpose: InvitationPurpose}> */
    public array $sent = [];

    /** @var list<array{operator: Operator, failures: list<CodeFailure>, superAdmins: list<OperatorEmail>}> */
    public array $blockedNotices = [];

    public function sendLink(OperatorEmail $email, string $name, #[\SensitiveParameter] string $token, InvitationPurpose $purpose): void
    {
        $this->sent[] = ['email' => $email->value, 'name' => $name, 'token' => $token, 'purpose' => $purpose];
    }

    public function operatorBlocked(Operator $blocked, array $failures, array $superAdmins): void
    {
        $this->blockedNotices[] = ['operator' => $blocked, 'failures' => $failures, 'superAdmins' => $superAdmins];
    }

    public function lastToken(): string
    {
        return $this->sent[array_key_last($this->sent)]['token'];
    }
}
