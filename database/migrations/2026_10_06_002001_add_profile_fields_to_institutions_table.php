<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifying and contact data of a school (plantel). All nullable:
     * institutions created before this migration have none of it, and the
     * selector/login flow only ever needs name + subdomain.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            // Código del plantel assigned by the MPPE (formerly "código DEA").
            $table->string('dea_code', 20)->nullable()->unique()->after('subdomain');
            // Registro de Información Fiscal, e.g. J-12345678-9.
            $table->string('rif', 12)->nullable()->unique()->after('dea_code');
            // Location from the global geographic catalog (F1-04); the state
            // and municipality are reachable through the parish.
            $table->foreignId('parish_id')->nullable()->after('rif')->constrained()->nullOnDelete();
            $table->text('address')->nullable()->after('parish_id');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            // Path on the default filesystem disk; uploading is a later feature.
            $table->string('logo_path')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations. Drops the profile data of every institution.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parish_id');
            $table->dropUnique(['dea_code']);
            $table->dropUnique(['rif']);
            $table->dropColumn(['dea_code', 'rif', 'address', 'phone', 'email', 'logo_path']);
        });
    }
};
