<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Primero creamos los catálogos y entidades independientes
        \App\Models\TipoEmpleado::factory(5)->create();
        \App\Models\ClaseServicio::factory(5)->create();
        \App\Models\Persona::factory(20)->create(); // Creamos 20 pacientes base
        \App\Models\Corte::factory(10)->create();

        // 2. Luego creamos las entidades transaccionales
        // Nota: Como configuramos los factories, Laravel es inteligente y si necesita 
        // una llave foránea, la creará automáticamente.
        \App\Models\Servicio::factory(15)->create();
        \App\Models\Empleado::factory(5)->create();
        \App\Models\Cita::factory(20)->create();
        \App\Models\Receta::factory(20)->create();
        \App\Models\Pago::factory(25)->create();
    }
}
