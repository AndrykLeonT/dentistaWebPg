<?php

namespace Tests\Feature;

use App\Models\MovimientoInventario;
use App\Models\ProductoInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function productoPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Guantes de nitrilo',
            'descripcion' => 'Caja para consulta',
            'unidadMedida' => 'caja',
            'stockInicial' => '10.00',
            'stockMinimo' => '3.00',
            'costoUnitario' => '120.50',
        ], $overrides);
    }

    private function crearProductoPorApi($empleado = null, array $overrides = []): ProductoInventario
    {
        $this->actingAs($empleado ?? $this->crearAdmin(), 'sanctum')
            ->postJson('/api/inventario/productos', $this->productoPayload($overrides))
            ->assertCreated();

        return ProductoInventario::orderByDesc('idProductoInventario')->firstOrFail();
    }

    private function movimientoPayload(ProductoInventario $producto, array $overrides = []): array
    {
        return array_merge([
            'idProductoInventario' => $producto->idProductoInventario,
            'tipoMovimiento' => 'entrada',
            'cantidad' => '5.00',
            'motivo' => 'Reposicion',
        ], $overrides);
    }

    public function test_admin_puede_crear_producto_y_stock_inicial_genera_movimiento(): void
    {
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/productos', $this->productoPayload())
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Guantes de nitrilo')
            ->assertJsonPath('data.stockActual', '10.00')
            ->assertJsonPath('data.estado', true);

        $producto = ProductoInventario::firstOrFail();
        $movimiento = MovimientoInventario::firstOrFail();
        $this->assertTrue($producto->estado);
        $this->assertSame('ajuste', $movimiento->tipoMovimiento);
        $this->assertSame('0.00', $movimiento->stockAnterior);
        $this->assertSame('10.00', $movimiento->stockNuevo);
        $this->assertSame($admin->idEmpleado, $movimiento->idEmpleado);
    }

    public function test_recepcionista_puede_crear_producto(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/inventario/productos', $this->productoPayload([
                'nombre' => 'Gasas',
            ]))->assertCreated();
    }

    public function test_dentista_y_usuario_sin_token_no_pueden_crear_producto(): void
    {
        $this->postJson('/api/inventario/productos', $this->productoPayload())
            ->assertUnauthorized();

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/inventario/productos', $this->productoPayload())
            ->assertForbidden();
    }

    public function test_crear_producto_rechaza_datos_invalidos_campos_derivados_y_nombre_activo_duplicado(): void
    {
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/productos', [
                'stockInicial' => '-1.00',
                'stockMinimo' => '-1.00',
                'costoUnitario' => '-5.00',
                'stockActual' => '999.00',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nombre',
                'unidadMedida',
                'stockInicial',
                'stockMinimo',
                'costoUnitario',
                'stockActual',
            ]);

        $this->crearProductoPorApi($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/productos', $this->productoPayload([
                'nombre' => ' guantes DE nitrilo ',
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_index_muestra_activos_y_resource_indica_bajo_stock(): void
    {
        ProductoInventario::factory()->create([
            'nombre' => 'Bajo',
            'stockActual' => '2.00',
            'stockMinimo' => '2.00',
            'estado' => true,
        ]);
        ProductoInventario::factory()->create(['estado' => false]);

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->getJson('/api/inventario/productos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bajoStock', true);
    }

    public function test_show_update_y_destroy_de_producto_respetan_baja_logica_y_stock_no_editable(): void
    {
        $admin = $this->crearAdmin();
        $producto = $this->crearProductoPorApi($admin);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/inventario/productos/{$producto->idProductoInventario}")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/inventario/productos/{$producto->idProductoInventario}", [
                'descripcion' => 'Actualizada',
                'stockMinimo' => '4.00',
                'stockActual' => '80.00',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['stockActual']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/inventario/productos/{$producto->idProductoInventario}", [
                'descripcion' => 'Actualizada',
                'stockMinimo' => '4.00',
            ])->assertOk()
            ->assertJsonPath('data.descripcion', 'Actualizada')
            ->assertJsonPath('data.stockActual', '10.00');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/inventario/productos/{$producto->idProductoInventario}")
            ->assertNoContent();

        $this->assertDatabaseHas('productos_inventario', [
            'idProductoInventario' => $producto->idProductoInventario,
            'estado' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/inventario/productos/{$producto->idProductoInventario}")
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/inventario/productos/{$producto->idProductoInventario}", ['descripcion' => 'No'])
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/inventario/productos/{$producto->idProductoInventario}")
            ->assertNotFound();
    }

    public function test_entrada_aumenta_stock_y_registra_empleado_autenticado(): void
    {
        $producto = ProductoInventario::factory()->create(['stockActual' => '10.00']);
        $recepcionista = $this->crearRecepcionista();

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto))
            ->assertCreated()
            ->assertJsonPath('data.stockAnterior', '10.00')
            ->assertJsonPath('data.stockNuevo', '15.00')
            ->assertJsonPath('data.empleado.id', $recepcionista->idEmpleado);

        $this->assertSame('15.00', $producto->fresh()->stockActual);
    }

    public function test_salida_disminuye_stock_y_no_permite_stock_negativo(): void
    {
        $producto = ProductoInventario::factory()->create(['stockActual' => '10.00']);
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto, [
                'tipoMovimiento' => 'salida',
                'cantidad' => '4.00',
            ]))->assertCreated()
            ->assertJsonPath('data.stockAnterior', '10.00')
            ->assertJsonPath('data.stockNuevo', '6.00');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto, [
                'tipoMovimiento' => 'salida',
                'cantidad' => '7.00',
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['cantidad']);

        $this->assertSame('6.00', $producto->fresh()->stockActual);
    }

    public function test_ajuste_establece_stock_fisico_y_admite_decimales(): void
    {
        $producto = ProductoInventario::factory()->create(['stockActual' => '10.00']);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto, [
                'tipoMovimiento' => 'ajuste',
                'cantidad' => '7.25',
            ]))->assertCreated()
            ->assertJsonPath('data.stockAnterior', '10.00')
            ->assertJsonPath('data.stockNuevo', '7.25');

        $this->assertSame('7.25', $producto->fresh()->stockActual);
    }

    public function test_movimiento_rechaza_cantidades_invalidas_y_campos_derivados(): void
    {
        $producto = ProductoInventario::factory()->create(['stockActual' => '10.00']);
        $admin = $this->crearAdmin();

        foreach (['entrada', 'salida'] as $tipo) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto, [
                    'tipoMovimiento' => $tipo,
                    'cantidad' => '0.00',
                ]))->assertUnprocessable()
                ->assertJsonValidationErrors(['cantidad']);
        }

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto, [
                'tipoMovimiento' => 'ajuste',
                'cantidad' => '-1.00',
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['cantidad']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto, [
                'idEmpleado' => $this->crearRecepcionista()->idEmpleado,
                'stockAnterior' => '1.00',
                'stockNuevo' => '999.00',
                'fechaMovimiento' => now()->toDateTimeString(),
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['idEmpleado', 'stockAnterior', 'stockNuevo', 'fechaMovimiento']);
    }

    public function test_producto_inactivo_no_recibe_movimientos_pero_su_historial_se_consulta(): void
    {
        $producto = ProductoInventario::factory()->create(['estado' => true, 'stockActual' => '10.00']);
        $recepcionista = $this->crearRecepcionista();

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto))
            ->assertCreated();

        $producto->update(['estado' => false]);

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto))
            ->assertNotFound();

        $this->actingAs($recepcionista, 'sanctum')
            ->getJson("/api/inventario/movimientos?idProductoInventario={$producto->idProductoInventario}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.producto.estado', false);
    }

    public function test_dentista_no_gestiona_inventario(): void
    {
        $producto = ProductoInventario::factory()->create();
        $dentista = $this->crearDentista();

        $this->actingAs($dentista, 'sanctum')
            ->getJson('/api/inventario/productos')
            ->assertForbidden();

        $this->actingAs($dentista, 'sanctum')
            ->postJson('/api/inventario/movimientos', $this->movimientoPayload($producto))
            ->assertForbidden();
    }
}
