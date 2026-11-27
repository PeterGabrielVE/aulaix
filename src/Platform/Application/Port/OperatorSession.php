<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorId;
use DateTimeImmutable;

/**
 * The panel's own session (guard «platform», ADR 0010).
 */
interface OperatorSession
{
    /**
     * Starts it as the operator, with a fresh session id, remembering its
     * session version and that a TOTP was just typed (PLT-NF04).
     */
    public function start(Operator $operator, DateTimeImmutable $now): void;

    public function end(): void;

    public function current(): ?OperatorId;

    public function confirmTotp(DateTimeImmutable $now): void;

    public function totpConfirmedAt(): ?DateTimeImmutable;

    /**
     * The operator's session version when the session started (PLT-R03.4).
     */
    public function sessionVersion(): ?int;

    public function touch(DateTimeImmutable $now): void;

    public function lastActivityAt(): ?DateTimeImmutable;
}
