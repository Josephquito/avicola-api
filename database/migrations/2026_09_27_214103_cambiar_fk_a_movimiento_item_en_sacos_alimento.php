<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacos_alimento', function (Blueprint $table) {
            $table->dropForeign('sacos_alimento_movimiento_id_foreign');
            $table->renameColumn('movimiento_id', 'movimiento_item_id');
        });

        Schema::table('sacos_alimento', function (Blueprint $table) {
            $table->foreign('movimiento_item_id')
                ->references('id')->on('movimiento_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sacos_alimento', function (Blueprint $table) {
            $table->dropForeign(['movimiento_item_id']);
            $table->renameColumn('movimiento_item_id', 'movimiento_id');
        });

        Schema::table('sacos_alimento', function (Blueprint $table) {
            $table->foreign('movimiento_id')
                ->references('id')->on('movimientos')
                ->nullOnDelete();
        });
    }
};