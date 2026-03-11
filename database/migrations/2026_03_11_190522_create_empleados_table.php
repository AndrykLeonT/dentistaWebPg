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
        Schema::create('empleados', function (Blueprint $table) {
            $table->id('idEmpleado');
            
            $table->unsignedBigInteger('idPersona'); 
            $table->foreign('idPersona')->references('idPersona')->on('personas')->onDelete('cascade');
            
            $table->unsignedBigInteger('idTipoEmpleado');
            $table->foreign('idTipoEmpleado')->references('idTipoEmpleado')->on('tipo_empleados');
            
            $table->string('usuario')->unique();
            $table->string('rfc')->unique();
            $table->string('contraseña');
            $table->string('palabraClave');
            $table->boolean('cambioContraseña')->default(0);
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
