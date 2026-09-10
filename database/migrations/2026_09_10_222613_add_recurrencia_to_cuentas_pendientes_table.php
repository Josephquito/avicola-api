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
        Schema::table('cuentas_pendientes', function (Blueprint $table) {
            $table->boolean('es_recurrente')->default(false);
            $table->string('frecuencia')->nullable(); // 'mensual' | 'trimestral' | 'anual'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuentas_pendientes', function (Blueprint $table) {
            $table->dropColumn(['es_recurrente', 'frecuencia']);
        });
    }
};