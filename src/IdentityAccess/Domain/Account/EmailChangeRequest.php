<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use DateTimeImmutable;

/**
 * A new email waiting to be verified (IDG-R05.2): only the hash of the link's
 * token is kept, and the link works for one day (IDG-NF04).
 */
final readonly class EmailChangeRequest
{
    private const LIFETIME = '+1 day';

    private function __construct(
        public EmailAddress $email,
        public string $tokenHash,
        public DateTimeImmutable $expiresAt,
    ) {}

    public static function issue(EmailAddress $email, DateTimeImmutable $now): IssuedEmailChange
    {
        $token = bin2hex(random_bytes(32));

        return new IssuedEmailChange(new self($email, self::hash($token), $now->modify(self::LIFETIME)), $token);
    }

    public static function reconstitute(EmailAddress $email, string $tokenHash, DateTimeImmutable $expiresAt): self
    {
        return new self($email, $tokenHash, $expiresAt);
    }

    public static function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(#[\SensitiveParameter] string $token, DateTimeImmutable $now): bool
    {
        return $now <= $this->expiresAt && hash_equals($this->tokenHash, self::hash($token));
    }
}
