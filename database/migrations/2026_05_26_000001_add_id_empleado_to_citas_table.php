<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->unsignedBigInteger('idEmpleado')->nullable()->after('idServicio');
            $table->foreign('idEmpleado')->references('idEmpleado')->on('empleados');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropForeign(['idEmpleado']);
            $table->dropColumn('idEmpleado');
        });
    }
};
