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
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // aporte_capital | retiro_capital | transferencia | cobro | pago | compra_aves | compra_alimento | compra_medicina | compra_muebles | venta_aves | venta_huevos
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->text('descripcion')->nullable();

            $table->foreignId('cuenta_efectivo_id')->nullable()->constrained('cuentas_efectivo');
            $table->foreignId('cuenta_destino_id')->nullable()->constrained('cuentas_efectivo');
            $table->foreignId('socio_id')->nullable()->constrained('users');
            $table->foreignId('contacto_id')->nullable()->constrained('contactos');

            $table->string('estado')->nullable(); // 'contado' | 'credito'

            $table->foreignId('user_id')->constrained('users'); // quién registró el movimiento

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};