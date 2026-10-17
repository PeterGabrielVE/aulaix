<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Domain\Account\Account;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * On-demand notifications to the account's own address. Every link points
 * to the central domain.
 */
final readonly class NotificationAccountMailer implements AccountMailer
{
    public function __construct(private Config $config) {}

    public function passwordResetLink(Account $account, #[\SensitiveParameter] string $token): void
    {
        $this->send($account, new ResetPasswordLink(
            $account->name->value,
            $token,
            URL::route('password.reset', ['token' => $token, 'email' => $account->email->value]),
            (int) $this->config->get('auth.passwords.users.expire'),
        ));
    }

    public function passwordChanged(Account $account): void
    {
        $this->send($account, new PasswordChangedNotice($account->name->value));
    }

    public function consolidationGuidance(Account $account): void
    {
        $this->send($account, new ConsolidationGuidance($account->name->value, URL::route('central.login')));
    }

    public function consolidationCode(Account $account, #[\SensitiveParameter] string $code): void
    {
        $this->send($account, new ConsolidationCode($account->name->value, $code));
    }

    public function accountsConsolidated(Account $account): void
    {
        $this->send($account, new AccountsConsolidatedNotice($account->name->value));
    }

    private function send(Account $account, BaseNotification $notification): void
    {
        Notification::route('mail', $account->email->value)->notify($notification);
    }
}
