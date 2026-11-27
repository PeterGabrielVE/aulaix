<?php

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\RecoveryCodeHasher;
use AulaX\Platform\Domain\RecoveryCodes;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-017 RSP-NF02: data migration that upgrades legacy SHA-256 recovery code
 * hashes to keyed HMAC fingerprints in place without invalidating existing codes.
 */
function rehashMigration(): object
{
    return require database_path('migrations/2026_11_27_000002_rehash_platform_operator_recovery_codes.php');
}

test('recovery codes generated before migration remain valid afterwards', function () {
    $repository = app(OperatorRepository::class);
    $id = $repository->save(Operator::invite('Ana', OperatorEmail::fromString('ana@aulax.ve'), OperatorRole::SuperAdmin));

    $plain = array_map(fn (int $n) => "k3f9q-2x7m{$n}", range(0, 9));
    foreach ($plain as $code) {
        DB::table('platform_operator_recovery_codes')->insert([
            'operator_id' => $id->value,
            'code_hash' => hash('sha256', RecoveryCodes::normalize($code)),
            'used_at' => null,
        ]);
    }

    config()->set('platform.recovery_code_key', 'migration-secret-key');
    rehashMigration()->up();

    $hasher = app(RecoveryCodeHasher::class);
    $stored = $repository->recoveryCodes($id);

    expect($stored->accepts('K3F9Q-2X7M0', $hasher))->toBeTrue()
        ->and($stored->accepts('k3f9q-2x7m9', $hasher))->toBeTrue()
        ->and(DB::table('platform_operator_recovery_codes')->value('code_hash'))
        ->not->toBe(hash('sha256', RecoveryCodes::normalize('k3f9q-2x7m0')));
});

test('fails before modifying rows when the key is missing', function () {
    $repository = app(OperatorRepository::class);
    $id = $repository->save(Operator::invite('Ana', OperatorEmail::fromString('ana@aulax.ve'), OperatorRole::SuperAdmin));
    $legacyHash = hash('sha256', RecoveryCodes::normalize('k3f9q-2x7m0'));

    DB::table('platform_operator_recovery_codes')->insert([
        'operator_id' => $id->value,
        'code_hash' => $legacyHash,
        'used_at' => null,
    ]);

    config()->set('platform.recovery_code_key', '');

    expect(fn () => rehashMigration()->up())->toThrow(RuntimeException::class, 'PLATFORM_RECOVERY_CODE_KEY')
        ->and(DB::table('platform_operator_recovery_codes')->where('operator_id', $id->value)->value('code_hash'))
        ->toBe($legacyHash);
});

test('down throws explaining the rollback procedure', function () {
    $repository = app(OperatorRepository::class);
    $id = $repository->save(Operator::invite('Ana', OperatorEmail::fromString('ana-down@aulax.ve'), OperatorRole::SuperAdmin));
    DB::table('platform_operator_recovery_codes')->insert([
        'operator_id' => $id->value,
        'code_hash' => hash('sha256', 'already-rehashed'),
        'used_at' => null,
    ]);

    expect(fn () => rehashMigration()->down())->toThrow(RuntimeException::class, 'Option A');
});
