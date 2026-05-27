<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\ConsumoServicio;
use App\Models\MovimientoInventario;
use App\Models\ProductoInventario;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class ConsumoInventarioCitaTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function crearCitaConRegla(array $productoOverrides = [], string $cantidad = '2.00'): array
    {
        $producto = ProductoInventario::factory()->create(array_merge([
            'stockActual' => '10.00',
            'estado' => true,
        ], $productoOverrides));
        $servicio = Servicio::factory()->create();
        $cita = Cita::factory()->create([
            'idServicio' => $servicio->idServicio,
            'estado' => true,
        ]);
        $regla = ConsumoServicio::factory()->create([
            'idServicio' => $servicio->idServicio,
            'idProductoInventario' => $producto->idProductoInventario,
            'cantidad' => $cantidad,
            'estado' => true,
        ]);

        return [$cita, $producto, $regla];
    }

    public function test_admin_y_recepcionista_pueden_consumir_inventario_de_cita(): void
    {
        [$cita, $producto] = $this->crearCitaConRegla();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Consumo de inventario aplicado correctamente.')
            ->assertJsonPath('movimientos.0.tipoMovimiento', 'salida')
            ->assertJsonPath('movimientos.0.stockAnterior', '10.00')
            ->assertJsonPath('movimientos.0.stockNuevo', '8.00');

        $this->assertSame('8.00', $producto->fresh()->stockActual);

        [$otraCita] = $this->crearCitaConRegla();
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson("/api/citas/{$otraCita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertOk();
    }

    public function test_consumo_restringe_dentista_sin_token_y_cita_inexistente(): void
    {
        [$cita] = $this->crearCitaConRegla();

        $this->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertUnauthorized();

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertForbidden();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/citas/999999/consumir-inventario', ['confirmar' => true])
            ->assertNotFound();
    }

    public function test_consumo_valida_confirmacion_cita_activa_y_reglas(): void
    {
        [$cita] = $this->crearCitaConRegla();
        $citaSinReglas = Cita::factory()->create(['estado' => true]);
        $citaInactiva = Cita::factory()->create(['estado' => false]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmar']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/citas/{$citaSinReglas->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idServicio']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/citas/{$citaInactiva->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertNotFound();
    }

    public function test_stock_insuficiente_no_crea_movimientos_ni_cambia_stock(): void
    {
        [$cita, $producto] = $this->crearCitaConRegla(['stockActual' => '1.00'], '2.00');

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cantidad']);

        $this->assertSame('1.00', $producto->fresh()->stockActual);
        $this->assertSame(0, MovimientoInventario::count());
    }

    public function test_segundo_consumo_devuelve_409_y_no_duplica_movimientos(): void
    {
        [$cita] = $this->crearCitaConRegla();
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertStatus(409);

        $this->assertSame(1, MovimientoInventario::where('tipoMovimiento', 'salida')->count());
    }

    public function test_producto_inactivo_en_regla_no_descuenta_stock(): void
    {
        [$cita, $producto] = $this->crearCitaConRegla(['estado' => false], '2.00');

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/citas/{$cita->idCita}/consumir-inventario", ['confirmar' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idProductoInventario']);

        $this->assertSame('10.00', $producto->fresh()->stockActual);
    }
}
