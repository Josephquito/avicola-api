<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_pendientes', function (Blueprint $table) {
            $table->foreignId('recurrencia_id')
                ->nullable()
                ->after('contacto_id')
                ->constrained('recurrencias')
                ->nullOnDelete();

            $table->dropColumn(['es_recurrente', 'frecuencia']);
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_pendientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrencia_id');
            $table->boolean('es_recurrente')->default(false);
            $table->string('frecuencia')->nullable();
        });
    }
};