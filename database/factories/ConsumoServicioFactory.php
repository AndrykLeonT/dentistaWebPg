<?php

namespace Database\Factories;

use App\Models\ProductoInventario;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConsumoServicio>
 */
class ConsumoServicioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idServicio' => Servicio::factory(),
            'idProductoInventario' => ProductoInventario::factory(),
            'cantidad' => '1.00',
            'estado' => true,
        ];
    }
}
