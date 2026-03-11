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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id('idPago');
            
            $table->unsignedBigInteger('idPersona'); 
            $table->foreign('idPersona')->references('idPersona')->on('personas');
            
            $table->unsignedBigInteger('idEmpleado'); 
            $table->foreign('idEmpleado')->references('idEmpleado')->on('empleados');
            
            $table->unsignedBigInteger('idCorte')->nullable(); 
            $table->foreign('idCorte')->references('idCorte')->on('cortes');

            $table->date('fechaRegistro');
            $table->decimal('total', 8, 2);
            $table->boolean('pagado')->default(0);
            $table->decimal('efectivo', 8, 2)->default(0);
            $table->decimal('tarjeta', 8, 2)->default(0);
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
