<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumo_inventario_cita_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idConsumoInventarioCita');
            $table->foreign('idConsumoInventarioCita', 'consumo_cita_mov_cons_fk')
                ->references('idConsumoInventarioCita')
                ->on('consumos_inventario_cita');
            $table->unsignedBigInteger('idMovimientoInventario');
            $table->foreign('idMovimientoInventario', 'consumo_cita_mov_mov_fk')
                ->references('idMovimientoInventario')
                ->on('movimientos_inventario');
            $table->timestamps();

            $table->unique(
                ['idConsumoInventarioCita', 'idMovimientoInventario'],
                'consumo_cita_movimiento_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumo_inventario_cita_movimientos');
    }
};
