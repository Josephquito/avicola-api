<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();

            $table->decimal('cantidad', 12, 2)->nullable();
            $table->integer('cantidad_sacos')->nullable();
            $table->decimal('peso_por_saco', 8, 2)->nullable();
            $table->integer('edad_inicial_dias')->nullable();
            $table->integer('cantidad_gallinas')->nullable();
            $table->integer('cantidad_gallos')->nullable();
            $table->integer('cantidad_huevos')->nullable();

            $table->decimal('precio_unitario', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->string('descripcion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_items');
    }
};