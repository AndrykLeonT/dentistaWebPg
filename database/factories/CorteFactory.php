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
            'fechaFin'    => fake()->dateTimeThisMonth(),
            'fDeCaja'     => fake()->randomFloat(2, 500, 1000),
            'tEfectivo'   => fake()->randomFloat(2, 1000, 10000),
            'tTarjeta'    => fake()->randomFloat(2, 1000, 10000),
            'correcto'    => fake()->boolean(90),
            'estado'      => 1,
        ];
    }

    public function abierto(): static
    {
        return $this->state([
            'fechaFin'  => null,
            'tEfectivo' => 0,
            'tTarjeta'  => 0,
        ]);
    }
}
