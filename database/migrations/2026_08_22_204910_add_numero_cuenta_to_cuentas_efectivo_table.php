<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cuentas_efectivo', function (Blueprint $table) {
            $table->string('numero_cuenta')->nullable()->after('tipo');
            $table->unique('numero_cuenta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuentas_efectivo', function (Blueprint $table) {
            $table->dropUnique(['numero_cuenta']);
            $table->dropColumn('numero_cuenta');
        });
    }
};