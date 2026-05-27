<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductoInventario>
 */
class ProductoInventarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(3, true),
            'descripcion' => fake()->sentence(),
            'unidadMedida' => 'pieza',
            'stockActual' => '10.00',
            'stockMinimo' => '2.00',
            'costoUnitario' => '25.50',
            'estado' => true,
        ];
    }
}
