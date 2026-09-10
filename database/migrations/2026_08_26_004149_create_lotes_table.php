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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->string('origen'); // 'compra' | 'nacimiento'
            $table->unsignedInteger('cantidad_gallinas')->default(0);
            $table->unsignedInteger('cantidad_gallos')->default(0);
            $table->unsignedInteger('cantidad_empollando')->default(0);
            $table->unsignedInteger('edad_inicial_dias')->default(0);
            $table->date('fecha_ingreso');
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos');
            $table->foreignId('lote_origen_id')->nullable()->constrained('lotes');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};