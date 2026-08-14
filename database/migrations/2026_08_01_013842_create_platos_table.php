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
    Schema::create('platos', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');
        $table->string('cat')->default('Ceviches');
        $table->text('desc')->nullable();
        $table->decimal('precio', 8, 2);
        $table->decimal('precioOferta', 8, 2)->nullable();
        $table->integer('stock')->default(10);
        $table->enum('estado', ['Disponible', 'Agotado'])->default('Disponible');
        $table->string('img')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platos');
    }
};
