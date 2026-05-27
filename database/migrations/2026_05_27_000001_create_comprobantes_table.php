<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id('idComprobante');
            $table->unsignedBigInteger('idPago')->unique();
            $table->foreign('idPago')->references('idPago')->on('pagos');
            $table->string('folio')->unique();
            $table->dateTime('fechaEmision');
            $table->decimal('total', 8, 2);
            $table->decimal('efectivo', 8, 2);
            $table->decimal('tarjeta', 8, 2);
            $table->boolean('estado')->default(true);
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
