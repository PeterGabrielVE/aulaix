<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * On-demand notifications, to the account's own address unless said
 * otherwise. Every link points to the central domain.
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

    public function emailChangeVerification(Account $account, EmailAddress $newEmail, #[\SensitiveParameter] string $token): void
    {
        $this->sendTo($newEmail, new EmailChangeVerification(
            $account->name->value,
            URL::route('central.account.email.confirm', ['token' => $token]),
        ));
    }

    public function emailAlreadyInUse(EmailAddress $email): void
    {
        $this->sendTo($email, new EmailAlreadyInUseNotice);
    }

    public function emailChanged(Account $before): void
    {
        $this->send($before, new EmailChangedNotice($before->name->value));
    }

    public function deletionScheduled(Account $account): void
    {
        $this->send($account, new DeletionScheduledNotice(
            $account->name->value,
            self::day($account->deletionRequestedAt),
            self::day($account->deletionDueAt),
            URL::route('central.login'),
        ));
    }

    public function deletionPostponed(Account $account, array $reasons): void
    {
        $retryOn = self::day($account->deletionDueAt);

        $this->send($account, new DeletionPostponedNotice("¡Hola, {$account->name->value}!", $reasons, $retryOn));

        $support = (string) $this->config->get('identity.support_email');

        if ($support !== '') {
            $this->sendTo(EmailAddress::fromString($support), new DeletionPostponedNotice("Cuenta #{$account->id->value}", $reasons, $retryOn));
        }
    }

    /**
     * A date as people in Venezuela read it.
     */
    private static function day(?DateTimeImmutable $date): string
    {
        return $date?->setTimezone(new DateTimeZone('America/Caracas'))->format('d/m/Y') ?? '';
    }

    private function send(Account $account, BaseNotification $notification): void
    {
        $this->sendTo($account->email, $notification);
    }

    private function sendTo(EmailAddress $email, BaseNotification $notification): void
    {
        Notification::route('mail', $email->value)->notify($notification);
    }
}
