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
        Schema::create('personas', function (Blueprint $table) {
            $table->id('idPersona'); // Tu llave primaria
            $table->string('nombre');
            $table->string('apellidoP');
            $table->string('apellidoM')->nullable(); // nullable permite dejarlo en blanco si alguien no tiene segundo apellido
            $table->string('celular');
            $table->string('correoElectronico')->unique();
            $table->date('fechaRegistro');
            $table->boolean('estado')->default(1);
            $table->timestamps(); // Crea las columnas created_at y updated_at que Laravel usa por defecto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
