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
        Schema::create('sacos_alimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('unidad_id')->constrained('unidades_medida');
            $table->decimal('peso', 8, 2);
            $table->string('estado')->default('en_espera'); // 'en_espera' | 'en_uso' | 'terminado'
            $table->date('fecha_compra');
            $table->date('fecha_inicio_uso')->nullable();
            $table->date('fecha_fin_uso')->nullable();
            $table->foreignId('movimiento_id')->constrained('movimientos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacos_alimento');
    }
};