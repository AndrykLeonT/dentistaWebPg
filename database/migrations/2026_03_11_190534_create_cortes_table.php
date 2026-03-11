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
        Schema::create('cortes', function (Blueprint $table) {
            $table->id('idCorte');
            $table->dateTime('fechaInicio');
            $table->dateTime('fechaFin')->nullable();
            $table->decimal('fDeCaja', 8, 2); 
            $table->decimal('tEfectivo', 8, 2)->default(0); 
            $table->decimal('tTarjeta', 8, 2)->default(0); 
            $table->boolean('correcto')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cortes');
    }
};
