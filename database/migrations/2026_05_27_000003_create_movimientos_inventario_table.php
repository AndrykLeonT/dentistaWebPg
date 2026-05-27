<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id('idMovimientoInventario');
            $table->unsignedBigInteger('idProductoInventario');
            $table->foreign('idProductoInventario')
                ->references('idProductoInventario')
                ->on('productos_inventario');
            $table->unsignedBigInteger('idEmpleado');
            $table->foreign('idEmpleado')->references('idEmpleado')->on('empleados');
            $table->string('tipoMovimiento', 20);
            $table->decimal('cantidad', 10, 2);
            $table->decimal('stockAnterior', 10, 2);
            $table->decimal('stockNuevo', 10, 2);
            $table->string('motivo', 500)->nullable();
            $table->dateTime('fechaMovimiento');
            $table->timestamps();

            $table->index(['idProductoInventario', 'fechaMovimiento'], 'mov_inv_producto_fecha_idx');
            $table->index('tipoMovimiento', 'mov_inv_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
