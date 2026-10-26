<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

/**
 * The page a link opens: who is enrolling, and the QR of the TOTP to
 * confirm. The secret is also shown, for apps that cannot scan.
 */
final readonly class EnrolmentView
{
    public function __construct(
        public string $name,
        public string $email,
        public bool $needsPassword,
        public string $secret,
        public string $qrSvg,
    ) {}
}
