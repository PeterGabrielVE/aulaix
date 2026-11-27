<?php

use AulaX\Shared\Application\TransactionManager;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;

it('keeps the changes and returns the result when the operation succeeds', function () {
    $result = app(TransactionManager::class)->run(function () {
        Institution::factory()->create(['subdomain' => 'confirmado']);

        return 'listo';
    });

    expect($result)->toBe('listo');
    $this->assertDatabaseHas('institutions', ['subdomain' => 'confirmado']);
});

it('undoes every change and rethrows when the operation fails', function () {
    $failingOperation = function () {
        Institution::factory()->create(['subdomain' => 'revertido']);

        throw new RuntimeException('falla a mitad de camino');
    };

    expect(fn () => app(TransactionManager::class)->run($failingOperation))
        ->toThrow(RuntimeException::class, 'falla a mitad de camino');

    $this->assertDatabaseMissing('institutions', ['subdomain' => 'revertido']);
});
