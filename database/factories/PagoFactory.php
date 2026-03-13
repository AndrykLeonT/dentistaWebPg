<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pago>
 */
class PagoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idPersona' => \App\Models\Persona::factory(),
            'idEmpleado' => \App\Models\Empleado::factory(),
            'idCorte' => \App\Models\Corte::factory(),
            'fechaRegistro' => fake()->date(),
            'total' => fake()->randomFloat(2, 500, 8000),
            'pagado' => fake()->boolean(80), // 80% de probabilidad de que ya esté pagado
            'efectivo' => fake()->randomFloat(2, 0, 4000),
            'tarjeta' => fake()->randomFloat(2, 0, 4000),
            'estado' => 1,
        ];
    }
}
