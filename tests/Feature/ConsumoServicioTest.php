<?php

namespace Tests\Feature;

use App\Models\ConsumoServicio;
use App\Models\ProductoInventario;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class ConsumoServicioTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idServicio' => Servicio::factory()->create()->idServicio,
            'idProductoInventario' => ProductoInventario::factory()->create()->idProductoInventario,
            'cantidad' => '2.00',
        ], $overrides);
    }

    public function test_admin_puede_crear_regla_y_no_modifica_stock(): void
    {
        $producto = ProductoInventario::factory()->create(['stockActual' => '10.00']);
        $servicio = Servicio::factory()->create(['nombre' => 'Limpieza']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/inventario/consumos-servicio', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idProductoInventario' => $producto->idProductoInventario,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.idServicio', $servicio->idServicio)
            ->assertJsonPath('data.servicio', 'Limpieza')
            ->assertJsonPath('data.producto', $producto->nombre)
            ->assertJsonPath('data.cantidad', '2.00')
            ->assertJsonPath('data.activo', true);

        $this->assertSame('10.00', $producto->fresh()->stockActual);
    }

    public function test_roles_no_autorizados_y_sin_token_no_crean_reglas(): void
    {
        $this->postJson('/api/inventario/consumos-servicio', $this->payload())
            ->assertUnauthorized();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/inventario/consumos-servicio', $this->payload())
            ->assertForbidden();

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/inventario/consumos-servicio', $this->payload())
            ->assertForbidden();
    }

    public function test_validaciones_de_regla(): void
    {
        $productoInactivo = ProductoInventario::factory()->create(['estado' => false]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/inventario/consumos-servicio', $this->payload([
                'idServicio' => 999999,
                'idProductoInventario' => 999999,
                'cantidad' => '0.00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idServicio', 'idProductoInventario', 'cantidad']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/inventario/consumos-servicio', $this->payload([
                'idProductoInventario' => $productoInactivo->idProductoInventario,
                'cantidad' => '-1.00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idProductoInventario', 'cantidad']);
    }

    public function test_duplicado_activo_devuelve_422(): void
    {
        $servicio = Servicio::factory()->create();
        $producto = ProductoInventario::factory()->create();
        ConsumoServicio::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idProductoInventario' => $producto->idProductoInventario,
            'estado' => true,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/inventario/consumos-servicio', $this->payload([
                'idServicio' => $servicio->idServicio,
                'idProductoInventario' => $producto->idProductoInventario,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idProductoInventario']);
    }

    public function test_crud_respeta_baja_logica(): void
    {
        $admin = $this->crearAdmin();
        $consumo = ConsumoServicio::factory()->create([
            'cantidad' => '1.00',
            'estado' => true,
        ]);
        ConsumoServicio::factory()->create(['estado' => false]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/inventario/consumos-servicio')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/inventario/consumos-servicio/{$consumo->idConsumoServicio}")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/inventario/consumos-servicio/{$consumo->idConsumoServicio}", [
                'cantidad' => '3.50',
            ])
            ->assertOk()
            ->assertJsonPath('data.cantidad', '3.50');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/inventario/consumos-servicio/{$consumo->idConsumoServicio}")
            ->assertNoContent();

        $this->assertDatabaseHas('consumos_servicio', [
            'idConsumoServicio' => $consumo->idConsumoServicio,
            'estado' => false,
        ]);

        foreach (['getJson', 'deleteJson'] as $method) {
            $this->actingAs($admin, 'sanctum')
                ->{$method}("/api/inventario/consumos-servicio/{$consumo->idConsumoServicio}")
                ->assertNotFound();
        }

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/inventario/consumos-servicio/{$consumo->idConsumoServicio}", [
                'cantidad' => '4.00',
            ])
            ->assertNotFound();
    }
}
