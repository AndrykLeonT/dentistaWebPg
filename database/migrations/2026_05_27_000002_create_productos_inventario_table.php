<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_inventario', function (Blueprint $table) {
            $table->id('idProductoInventario');
            $table->string('nombre');
            $table->string('descripcion', 500)->nullable();
            $table->string('unidadMedida', 50);
            $table->decimal('stockActual', 10, 2)->default(0);
            $table->decimal('stockMinimo', 10, 2)->default(0);
            $table->decimal('costoUnitario', 10, 2)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['estado', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_inventario');
    }
};
