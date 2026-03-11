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
        Schema::create('citas', function (Blueprint $table) {
            $table->id('idCita');
            
            $table->unsignedBigInteger('idPersona'); 
            $table->foreign('idPersona')->references('idPersona')->on('personas');
            
            $table->unsignedBigInteger('idServicio');
            $table->foreign('idServicio')->references('idServicio')->on('servicios');
            
            // El motivo ahora es simplemente un texto
            $table->string('motivo'); 
            
            $table->date('fechaRegistro');
            $table->date('fechaProgramada');
            $table->time('hora');
            $table->time('duracion')->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
