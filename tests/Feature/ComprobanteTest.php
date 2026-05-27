<?php

namespace Tests\Feature;

use App\Models\Comprobante;
use App\Models\Corte;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class ComprobanteTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function pagoValido(array $overrides = []): Pago
    {
        return Pago::factory()->create(array_merge([
            'idCorte' => Corte::factory()->abierto()->create()->idCorte,
            'total' => 500.50,
            'efectivo' => 300.25,
            'tarjeta' => 200.25,
            'pagado' => true,
            'estado' => true,
        ], $overrides));
    }

    private function emitir(Pago $pago, $empleado = null)
    {
        return $this->actingAs($empleado ?? $this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/comprobantes', ['idPago' => $pago->idPago]);
    }

    public function test_admin_puede_emitir_comprobante_para_pago_valido(): void
    {
        $pago = $this->pagoValido();

        $this->emitir($pago, $this->crearAdmin())
            ->assertCreated()
            ->assertJsonPath('data.pago.idPago', $pago->idPago)
            ->assertJsonPath('data.total', '500.50')
            ->assertJsonPath('data.estado', 'emitido')
            ->assertJsonStructure(['data' => ['idComprobante', 'folio', 'fechaEmision', 'paciente', 'cajero']]);

        $comprobante = Comprobante::firstOrFail();
        $this->assertSame($pago->idPago, $comprobante->idPago);
        $this->assertStringStartsWith('CMP-', $comprobante->folio);
    }

    public function test_recepcionista_puede_emitir_comprobante_para_pago_valido(): void
    {
        $this->emitir($this->pagoValido())
            ->assertCreated();
    }

    public function test_dentista_no_puede_emitir_comprobante(): void
    {
        $this->emitir($this->pagoValido(), $this->crearDentista())
            ->assertForbidden();
    }

    public function test_dentista_no_puede_consultar_ni_cancelar_comprobantes(): void
    {
        $pago = $this->pagoValido();
        $this->emitir($pago)->assertCreated();
        $comprobante = Comprobante::firstOrFail();
        $dentista = $this->crearDentista();

        $this->actingAs($dentista, 'sanctum')
            ->getJson('/api/comprobantes')
            ->assertForbidden();

        $this->actingAs($dentista, 'sanctum')
            ->deleteJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertForbidden();
    }

    public function test_usuario_sin_token_no_puede_emitir_comprobante(): void
    {
        $this->postJson('/api/comprobantes', ['idPago' => $this->pagoValido()->idPago])
            ->assertUnauthorized();
    }

    public function test_no_se_puede_emitir_para_pago_inexistente(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/comprobantes', ['idPago' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idPago']);
    }

    public function test_no_se_puede_emitir_para_pago_inactivo(): void
    {
        $this->emitir($this->pagoValido(['estado' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idPago']);
    }

    public function test_no_se_puede_emitir_para_pago_historico_no_liquidado(): void
    {
        $pago = $this->pagoValido([
            'total' => 500.00,
            'efectivo' => 100.00,
            'tarjeta' => 100.00,
            'pagado' => true,
        ]);

        $this->emitir($pago)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idPago']);
    }

    public function test_no_se_puede_emitir_doble_comprobante_incluso_si_el_anterior_fue_cancelado(): void
    {
        $pago = $this->pagoValido();
        $recepcionista = $this->crearRecepcionista();

        $this->emitir($pago, $recepcionista)->assertCreated();
        $comprobante = Comprobante::firstOrFail();

        $this->actingAs($recepcionista, 'sanctum')
            ->deleteJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertNoContent();

        $this->emitir($pago, $recepcionista)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idPago']);
    }

    public function test_folios_generados_para_pagos_distintos_son_unicos(): void
    {
        $recepcionista = $this->crearRecepcionista();

        $this->emitir($this->pagoValido(), $recepcionista)->assertCreated();
        $this->emitir($this->pagoValido(), $recepcionista)->assertCreated();

        $this->assertCount(2, Comprobante::pluck('folio')->unique());
    }

    public function test_frontend_no_puede_imponer_folio_montos_fecha_o_estado(): void
    {
        $pago = $this->pagoValido();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/comprobantes', [
                'idPago' => $pago->idPago,
                'folio' => 'FALSO-001',
                'fechaEmision' => now()->toDateTimeString(),
                'total' => 1,
                'efectivo' => 1,
                'tarjeta' => 0,
                'estado' => false,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'folio',
                'fechaEmision',
                'total',
                'efectivo',
                'tarjeta',
                'estado',
            ]);
    }

    public function test_comprobante_conserva_snapshot_si_pago_abierto_se_actualiza_validamente(): void
    {
        $pago = $this->pagoValido();
        $admin = $this->crearAdmin();
        $this->emitir($pago, $admin)->assertCreated();
        $comprobante = Comprobante::firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/pagos/{$pago->idPago}", [
                'total' => 700.00,
                'efectivo' => 700.00,
                'tarjeta' => 0.00,
            ])->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertOk()
            ->assertJsonPath('data.total', '500.50')
            ->assertJsonPath('data.efectivo', '300.25')
            ->assertJsonPath('data.tarjeta', '200.25');
    }

    public function test_index_y_show_retornan_comprobantes_activos_con_pago_y_paciente(): void
    {
        $pago = $this->pagoValido();
        $admin = $this->crearAdmin();

        $this->emitir($pago, $admin)->assertCreated();
        $comprobante = Comprobante::firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/comprobantes?idPago={$pago->idPago}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pago.idPago', $pago->idPago);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertOk()
            ->assertJsonPath('data.paciente.id', $pago->idPersona);
    }

    public function test_cancelar_comprobante_es_baja_logica_y_no_modifica_pago_ni_corte(): void
    {
        $pago = $this->pagoValido();
        $corte = $pago->corte;
        $this->emitir($pago)->assertCreated();
        $comprobante = Comprobante::firstOrFail();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->deleteJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertNoContent();

        $this->assertFalse($comprobante->fresh()->estado);
        $this->assertTrue($pago->fresh()->estado);
        $this->assertSame('500.50', $pago->fresh()->total);
        $this->assertNull($corte->fresh()->fechaFin);
    }

    public function test_comprobante_cancelado_no_se_lista_ni_se_cancela_dos_veces(): void
    {
        $pago = $this->pagoValido();
        $admin = $this->crearAdmin();
        $this->emitir($pago, $admin)->assertCreated();
        $comprobante = Comprobante::firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertNoContent();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/comprobantes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/comprobantes/{$comprobante->idComprobante}")
            ->assertNotFound();
    }

    public function test_se_puede_emitir_comprobante_de_pago_en_corte_cerrado(): void
    {
        $corte = Corte::factory()->create(['fechaFin' => now()]);
        $pago = $this->pagoValido(['idCorte' => $corte->idCorte]);

        $this->emitir($pago)
            ->assertCreated()
            ->assertJsonPath('data.pago.idPago', $pago->idPago);

        $this->assertNotNull($corte->fresh()->fechaFin);
        $this->assertTrue($pago->fresh()->estado);
    }
}
