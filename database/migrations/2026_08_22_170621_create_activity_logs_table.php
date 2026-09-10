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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');       // created | updated | deleted
            $table->string('model_type');   // ej. "Product", "BirdLot"
            $table->unsignedBigInteger('model_id');
            $table->text('description');    // ej. "Erik Quito creó el producto Maíz"
            $table->json('changes')->nullable(); // valores antes/después (solo en updated)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};