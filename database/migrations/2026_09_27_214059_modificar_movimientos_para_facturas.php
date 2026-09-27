<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El enum de tipo en Postgres suele ser un check constraint o
        // un tipo real; si tu columna 'tipo' es un string simple (varchar),
        // este bloque no aplica y puedes borrarlo — ajusta según cómo
        // esté definida hoy esa columna.
        Schema::table('movimientos', function (Blueprint $table) {
            $table->boolean('anulado')->default(false)->after('user_id');
            $table->timestamp('anulado_en')->nullable()->after('anulado');
            $table->string('anulado_motivo')->nullable()->after('anulado_en');

            $table->dropColumn('cantidad_huevos');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn(['anulado', 'anulado_en', 'anulado_motivo']);
            $table->integer('cantidad_huevos')->nullable();
        });
    }
};