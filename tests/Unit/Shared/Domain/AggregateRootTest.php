<?php

use AulaX\Shared\Domain\AggregateRoot;
use AulaX\Shared\Domain\DomainEvent;

function recordedEvent(string $name): DomainEvent
{
    return new readonly class($name) implements DomainEvent
    {
        public function __construct(public string $name) {}

        public function occurredAt(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-10-10 08:00:00');
        }
    };
}

function aggregate(): object
{
    return new class
    {
        use AggregateRoot;

        public function happen(DomainEvent $event): void
        {
            $this->recordThat($event);
        }
    };
}

it('releases the recorded events in the order they happened', function () {
    $aggregate = aggregate();
    $first = recordedEvent('first');
    $second = recordedEvent('second');

    $aggregate->happen($first);
    $aggregate->happen($second);

    expect($aggregate->releaseEvents())->toBe([$first, $second]);
});

it('does not release the same events twice', function () {
    $aggregate = aggregate();
    $aggregate->happen(recordedEvent('first'));

    $aggregate->releaseEvents();

    expect($aggregate->releaseEvents())->toBe([]);
});
