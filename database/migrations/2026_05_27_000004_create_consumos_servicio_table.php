<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumos_servicio', function (Blueprint $table) {
            $table->id('idConsumoServicio');
            $table->unsignedBigInteger('idServicio');
            $table->foreign('idServicio')->references('idServicio')->on('servicios');
            $table->unsignedBigInteger('idProductoInventario');
            $table->foreign('idProductoInventario')
                ->references('idProductoInventario')
                ->on('productos_inventario');
            $table->decimal('cantidad', 10, 2);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['estado', 'idServicio'], 'consumos_servicio_estado_servicio_idx');
            $table->index(['idProductoInventario', 'estado'], 'consumos_servicio_producto_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_servicio');
    }
};
