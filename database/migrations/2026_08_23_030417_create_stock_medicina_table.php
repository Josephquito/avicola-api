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
        Schema::create('stock_medicina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('unidad_id')->constrained('unidades_medida');
            $table->decimal('cantidad', 10, 2); // positiva = entrada, negativa = salida
            $table->string('tipo_movimiento'); // 'compra' | 'consumo'
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos');
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_medicina');
    }
};