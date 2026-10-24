<?php

use AulaX\Shared\Domain\Clock;

test('the clock follows the application time, so tests can move it', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-10 08:30:00', new DateTimeZone('UTC')));

    expect(app(Clock::class)->now()->format('Y-m-d H:i:s'))->toBe('2026-10-10 08:30:00');
});
