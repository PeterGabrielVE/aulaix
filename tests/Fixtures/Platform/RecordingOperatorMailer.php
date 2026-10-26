<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorEmail;

/**
 * The links sent, with their token, so a test can open them.
 */
final class RecordingOperatorMailer implements OperatorMailer
{
    /** @var list<array{email: string, name: string, token: string, purpose: InvitationPurpose}> */
    public array $sent = [];

    public function sendLink(OperatorEmail $email, string $name, string $token, InvitationPurpose $purpose): void
    {
        $this->sent[] = ['email' => $email->value, 'name' => $name, 'token' => $token, 'purpose' => $purpose];
    }

    public function lastToken(): string
    {
        return $this->sent[array_key_last($this->sent)]['token'];
    }
}
