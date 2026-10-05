<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Global catalog (no institution_id): shared by every tenant, not RLS-scoped.
    // "code" is an internal reference code, not an official DIVIPOLA number —
    // see database/seeders/data/venezuela-divisions.json for the seed data caveat.
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
