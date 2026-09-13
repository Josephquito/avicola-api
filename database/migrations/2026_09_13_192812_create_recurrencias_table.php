<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrencias', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['por_cobrar', 'por_pagar']);
            $table->foreignId('contacto_id')->constrained('contactos');
            $table->string('concepto');
            $table->decimal('monto_base', 12, 2);
            $table->enum('frecuencia', ['mensual', 'trimestral', 'anual']);
            $table->date('fecha_inicio');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrencias');
    }
};