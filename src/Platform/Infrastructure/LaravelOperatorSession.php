<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorId;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;

/**
 * The «platform» guard and the panel's session, on admin.APP_DOMAIN's own
 * cookie (ADR 0010).
 */
final readonly class LaravelOperatorSession implements OperatorSession
{
    public const string VERSION = 'platform.session_version';

    public const string TOTP_CONFIRMED_AT = 'platform.totp_confirmed_at';

    public const string LAST_ACTIVITY = 'platform.last_activity';

    public function __construct(
        private AuthFactory $auth,
        private Session $session,
    ) {}

    public function start(Operator $operator, DateTimeImmutable $now): void
    {
        $this->guard()->loginUsingId($operator->id->value);
        $this->session->regenerate();
        $this->session->put(self::VERSION, $operator->sessionVersion);
        $this->session->put(self::TOTP_CONFIRMED_AT, $now->getTimestamp());
        $this->session->put(self::LAST_ACTIVITY, $now->getTimestamp());
    }

    public function end(): void
    {
        $this->guard()->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }

    public function current(): ?OperatorId
    {
        $id = $this->guard()->id();

        return $id === null ? null : OperatorId::fromInt((int) $id);
    }

    public function confirmTotp(DateTimeImmutable $now): void
    {
        $this->session->put(self::TOTP_CONFIRMED_AT, $now->getTimestamp());
    }

    public function totpConfirmedAt(): ?DateTimeImmutable
    {
        $at = $this->session->get(self::TOTP_CONFIRMED_AT);

        return is_int($at) ? (new DateTimeImmutable)->setTimestamp($at) : null;
    }

    public function sessionVersion(): ?int
    {
        $version = $this->session->get(self::VERSION);

        return is_int($version) ? $version : null;
    }

    public function touch(DateTimeImmutable $now): void
    {
        $this->session->put(self::LAST_ACTIVITY, $now->getTimestamp());
    }

    public function lastActivityAt(): ?DateTimeImmutable
    {
        $at = $this->session->get(self::LAST_ACTIVITY);

        return is_int($at) ? (new DateTimeImmutable)->setTimestamp($at) : null;
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard('platform');
        assert($guard instanceof StatefulGuard);

        return $guard;
    }
}
