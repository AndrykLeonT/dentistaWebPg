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
        Schema::create('servicios', function (Blueprint $table) {
            $table->id('idServicio');
            
            $table->unsignedBigInteger('idClaseServicio');
            $table->foreign('idClaseServicio')->references('idClaseServicio')->on('clase_servicios');
            
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('costo', 8, 2); 
            $table->time('duracion');
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
