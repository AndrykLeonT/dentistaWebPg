<?php

namespace Tests\Feature;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class PagoTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idPersona' => Persona::factory()->create()->idPersona,
            'total' => 500.00,
            'efectivo' => 300.00,
            'tarjeta' => 200.00,
        ], $overrides);
    }

    private function pagoEnCorte(Corte $corte, array $overrides = []): Pago
    {
        return Pago::factory()->create(array_merge([
            'idCorte' => $corte->idCorte,
            'total' => 500.00,
            'efectivo' => 300.00,
            'tarjeta' => 200.00,
            'pagado' => true,
            'estado' => true,
        ], $overrides));
    }

    public function test_recepcionista_puede_registrar_pago_mixto_con_corte_abierto(): void
    {
        $corte = Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.corte.id', $corte->idCorte)
            ->assertJsonPath('data.pagado', true)
            ->assertJsonPath('data.pendiente', 0);
    }

    public function test_crear_pago_correcto_con_efectivo_completo(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload(['efectivo' => 500.00, 'tarjeta' => 0.00]))
            ->assertCreated()
            ->assertJsonPath('data.pagado', true)
            ->assertJsonPath('data.pendiente', 0);
    }

    public function test_crear_pago_correcto_con_tarjeta_completa(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload(['efectivo' => 0.00, 'tarjeta' => 500.00]))
            ->assertCreated()
            ->assertJsonPath('data.pagado', true);
    }

    public function test_crear_pago_correcto_con_decimales(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload([
                'total' => 500.50,
                'efectivo' => 300.25,
                'tarjeta' => 200.25,
            ]))->assertCreated()
            ->assertJsonPath('data.pendiente', 0);
    }

    public function test_pago_toma_id_empleado_del_usuario_autenticado(): void
    {
        Corte::factory()->abierto()->create();
        $recepcionista = $this->crearRecepcionista();

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/pagos', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.empleado.id', $recepcionista->idEmpleado);
    }

    public function test_admin_puede_registrar_pago_valido(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/pagos', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.pagado', true);
    }

    public function test_rechaza_pago_incompleto(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload(['efectivo' => 100.00, 'tarjeta' => 100.00]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total']);
    }

    public function test_rechaza_pago_excedido(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload(['total' => 100.00, 'efectivo' => 200.00, 'tarjeta' => 0.00]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total']);
    }

    public function test_rechaza_total_cero_o_negativo(): void
    {
        Corte::factory()->abierto()->create();
        $usuario = $this->crearRecepcionista();

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/pagos', $this->payload(['total' => 0, 'efectivo' => 0, 'tarjeta' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total']);

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/pagos', $this->payload(['total' => -100, 'efectivo' => 0, 'tarjeta' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total']);
    }

    public function test_rechaza_montos_negativos(): void
    {
        Corte::factory()->abierto()->create();
        $usuario = $this->crearRecepcionista();

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/pagos', $this->payload(['total' => 100, 'efectivo' => -10, 'tarjeta' => 110]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['efectivo']);

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/pagos', $this->payload(['total' => 100, 'efectivo' => 110, 'tarjeta' => -10]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tarjeta']);
    }

    public function test_registrar_pago_sin_corte_abierto_retorna_422(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No hay un corte de caja abierto. Abre un corte antes de registrar pagos.');
    }

    public function test_crear_pago_rechaza_campos_derivados_por_frontend(): void
    {
        Corte::factory()->abierto()->create();
        $otroEmpleado = $this->crearAdmin();
        $otroCorte = Corte::factory()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload([
                'idEmpleado' => $otroEmpleado->idEmpleado,
                'idCorte' => $otroCorte->idCorte,
                'pagado' => false,
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['idEmpleado', 'idCorte', 'pagado']);
    }

    public function test_pagado_no_permita_aprobar_un_pago_invalido(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/pagos', $this->payload([
                'efectivo' => 100.00,
                'tarjeta' => 100.00,
                'pagado' => true,
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['total', 'pagado']);
    }

    public function test_admin_puede_actualizar_pago_de_corte_abierto_con_montos_validos(): void
    {
        $pago = $this->pagoEnCorte(Corte::factory()->abierto()->create());

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", [
                'total' => 600.00,
                'efectivo' => 100.00,
                'tarjeta' => 500.00,
            ])->assertOk()
            ->assertJsonPath('data.pagado', true)
            ->assertJsonPath('data.pendiente', 0);
    }

    public function test_actualizar_pago_rechaza_montos_inconsistentes(): void
    {
        $pago = $this->pagoEnCorte(Corte::factory()->abierto()->create());
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", [
                'total' => 500.00,
                'efectivo' => 100.00,
                'tarjeta' => 100.00,
            ])->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", [
                'total' => 100.00,
                'efectivo' => 200.00,
                'tarjeta' => 0.00,
            ])->assertUnprocessable();
    }

    public function test_actualizar_pago_rechaza_cambio_de_corte_y_estado_pagado(): void
    {
        $pago = $this->pagoEnCorte(Corte::factory()->abierto()->create());
        $otroCorte = Corte::factory()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", [
                'idCorte' => $otroCorte->idCorte,
                'pagado' => false,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['idCorte', 'pagado']);

        $this->assertSame($pago->idCorte, $pago->fresh()->idCorte);
    }

    public function test_no_se_puede_editar_pago_de_corte_cerrado(): void
    {
        $pago = $this->pagoEnCorte(Corte::factory()->create(['fechaFin' => now()]));

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", [
                'total' => 600.00,
                'efectivo' => 600.00,
                'tarjeta' => 0.00,
            ])->assertUnprocessable();
    }

    public function test_no_se_puede_desactivar_pago_de_corte_cerrado(): void
    {
        $pago = $this->pagoEnCorte(Corte::factory()->create(['fechaFin' => now()]));

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->deleteJson("/api/pagos/{$pago->idPago}")
            ->assertUnprocessable();

        $this->assertTrue($pago->fresh()->estado);
    }

    public function test_no_se_puede_mover_pago_de_corte_cerrado(): void
    {
        $pago = $this->pagoEnCorte(Corte::factory()->create(['fechaFin' => now()]));
        $otroCorte = Corte::factory()->abierto()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", ['idCorte' => $otroCorte->idCorte])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idCorte']);

        $this->assertNotSame($otroCorte->idCorte, $pago->fresh()->idCorte);
    }

    public function test_dentista_no_puede_ver_pagos(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->getJson('/api/pagos')
            ->assertForbidden();
    }
}
