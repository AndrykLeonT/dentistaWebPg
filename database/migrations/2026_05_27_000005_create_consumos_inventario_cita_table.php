<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumos_inventario_cita', function (Blueprint $table) {
            $table->id('idConsumoInventarioCita');
            $table->unsignedBigInteger('idCita')->unique();
            $table->foreign('idCita')->references('idCita')->on('citas');
            $table->unsignedBigInteger('idEmpleado');
            $table->foreign('idEmpleado')->references('idEmpleado')->on('empleados');
            $table->dateTime('fechaConsumo');
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['estado', 'fechaConsumo'], 'consumo_cita_estado_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_inventario_cita');
    }
};
