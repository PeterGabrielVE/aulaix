<?php

use AulaX\Shared\Application\EventBus;
use AulaX\Shared\Application\TransactionManager;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Shared\SomethingHappened;

it('publishes an event right away when no transaction is open', function () {
    Event::fake([SomethingHappened::class]);

    app(EventBus::class)->publish(new SomethingHappened('fuera de transacción'));

    Event::assertDispatched(SomethingHappened::class, fn (SomethingHappened $event) => $event->what === 'fuera de transacción');
});

it('publishes the events of a transaction only once it commits', function () {
    Event::fake([SomethingHappened::class]);
    $dispatchedInsideTransaction = null;

    app(TransactionManager::class)->run(function () use (&$dispatchedInsideTransaction) {
        app(EventBus::class)->publish(new SomethingHappened('primero'), new SomethingHappened('segundo'));

        $dispatchedInsideTransaction = Event::dispatched(SomethingHappened::class)->count();
    });

    expect($dispatchedInsideTransaction)->toBe(0);
    Event::assertDispatchedTimes(SomethingHappened::class, 2);
});

it('does not publish the events of a transaction that rolls back', function () {
    Event::fake([SomethingHappened::class]);

    try {
        app(TransactionManager::class)->run(function () {
            app(EventBus::class)->publish(new SomethingHappened('nunca'));

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // The rollback is the point of the test.
    }

    Event::assertNotDispatched(SomethingHappened::class);
});
