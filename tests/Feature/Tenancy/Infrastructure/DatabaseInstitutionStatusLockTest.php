<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\InstitutionStatusLock;
use AulaX\Tenancy\Infrastructure\Persistence\DatabaseInstitutionStatusLock;
use Illuminate\Database\ConnectionInterface;

/**
 * SPEC-017 RSP-R08.1, NF04: transaction-scoped advisory locks for institution
 * status require an active transaction.
 */
test('acquires shared and exclusive locks inside a transaction and refuses outside one', function () {
    $lock = app(InstitutionStatusLock::class);
    $id = InstitutionId::fromInt(42);

    // RefreshDatabase wraps this test in an open transaction.
    $lock->shared($id);
    $lock->exclusive($id);

    $noTx = Mockery::mock(ConnectionInterface::class);
    $noTx->shouldReceive('transactionLevel')->andReturn(0);
    $unwrapped = new DatabaseInstitutionStatusLock($noTx);

    expect(fn () => $unwrapped->shared($id))->toThrow(LogicException::class)
        ->and(fn () => $unwrapped->exclusive($id))->toThrow(LogicException::class);
});
