<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Corte>
 */
class CorteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fechaInicio' => fake()->dateTimeThisMonth(),
            'fechaFin' => fake()->dateTimeThisMonth(),
            'fDeCaja' => fake()->randomFloat(2, 500, 1000), // Fondo de caja inicial
            'tEfectivo' => fake()->randomFloat(2, 1000, 10000), // Total efectivo
            'tTarjeta' => fake()->randomFloat(2, 1000, 10000), // Total tarjeta
            'correcto' => fake()->boolean(90), // 90% de probabilidad de que el corte sea correcto
        ];
    }
}
