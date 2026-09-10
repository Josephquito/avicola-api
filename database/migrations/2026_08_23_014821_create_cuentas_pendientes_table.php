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
        Schema::create('cuentas_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // 'por_cobrar' | 'por_pagar'
            $table->foreignId('contacto_id')->constrained('contactos');
            $table->decimal('monto_original', 12, 2);
            $table->decimal('saldo_pendiente', 12, 2);
            $table->foreignId('movimiento_origen_id')->nullable()->constrained('movimientos');
            $table->date('fecha');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas_pendientes');
    }
};