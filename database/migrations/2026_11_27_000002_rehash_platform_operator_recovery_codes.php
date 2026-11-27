<?php

use AulaX\Platform\Infrastructure\KeyedRecoveryCodeHasher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-017 RSP-NF02: upgrades stored SHA-256 recovery code hashes to keyed
 * HMAC-SHA256 fingerprints so existing codes stay valid without operators
 * having to regenerate them. Irreversible by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        $secret = trim((string) config('platform.recovery_code_key'));

        if ($secret === '') {
            throw new RuntimeException('PLATFORM_RECOVERY_CODE_KEY must be configured before migrating platform_operator_recovery_codes.');
        }

        DB::transaction(function () use ($secret) {
            DB::table('platform_operator_recovery_codes')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($secret) {
                    foreach ($rows as $row) {
                        DB::table('platform_operator_recovery_codes')
                            ->where('id', $row->id)
                            ->update([
                                'code_hash' => KeyedRecoveryCodeHasher::rehash((string) $row->code_hash, $secret),
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Cannot reverse keyed HMAC recovery code hashes. '
            .'Option A: restore platform_operator_recovery_codes from the pre-migration backup. '
            .'Option B: have each operator regenerate its recovery codes (TOTP continues to work).'
        );
    }
};
