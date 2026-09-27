<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropForeign(['movimiento_id']);
            $table->renameColumn('movimiento_id', 'movimiento_item_id');
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->foreign('movimiento_item_id')
                ->references('id')->on('movimiento_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropForeign(['movimiento_item_id']);
            $table->renameColumn('movimiento_item_id', 'movimiento_id');
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->foreign('movimiento_id')
                ->references('id')->on('movimientos')
                ->nullOnDelete();
        });
    }
};