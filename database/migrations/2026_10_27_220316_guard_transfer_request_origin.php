<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;

/**
 * SPEC-015 RLT-R05 (ADR 0008): the origin of a transfer request is set only
 * by the institution that answers it, once. No data changes: the
 * application never wrote anything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        RowLevelSecurity::guardAddressedOrigin('transfer_requests');
    }

    public function down(): void
    {
        RowLevelSecurity::unguardAddressedOrigin('transfer_requests');
    }
};
