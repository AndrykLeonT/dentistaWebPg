<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->boolean('estado')->default(1)->after('correcto');
        });
    }

    public function down(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
