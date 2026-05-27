<?php

namespace Tests\Feature;

use App\Models\Corte;
use App\Models\Pago;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class CorteTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_recepcionista_puede_abrir_corte(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/cortes', ['fDeCaja' => 500.00])
            ->assertCreated()
            ->assertJsonPath('data.fechaFin', null);
    }

    public function test_no_se_puede_abrir_segundo_corte_con_uno_abierto(): void
    {
        Corte::factory()->abierto()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/cortes', ['fDeCaja' => 300.00])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya existe un corte de caja abierto. Ciérralo antes de abrir uno nuevo.');
    }

    public function test_activo_retorna_el_corte_abierto(): void
    {
        $corte = Corte::factory()->abierto()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/cortes/activo')
            ->assertOk()
            ->assertJsonPath('data.id', $corte->idCorte);
    }

    public function test_activo_retorna_404_cuando_no_hay_corte_abierto(): void
    {
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->getJson('/api/cortes/activo')
            ->assertNotFound();
    }

    public function test_cerrar_corte_calcula_totales_de_pagos_activos(): void
    {
        $corte = Corte::factory()->abierto()->create();
        $persona = Persona::factory()->create();
        $admin = $this->crearAdmin();

        foreach ([
            ['total' => 300.00, 'efectivo' => 300.00, 'tarjeta' => 0.00],
            ['total' => 200.00, 'efectivo' => 0.00, 'tarjeta' => 200.00],
            ['total' => 150.00, 'efectivo' => 100.00, 'tarjeta' => 50.00],
        ] as $montos) {
            Pago::factory()->create($montos + [
                'idCorte' => $corte->idCorte,
                'idPersona' => $persona->idPersona,
                'idEmpleado' => $admin->idEmpleado,
                'pagado' => true,
                'estado' => true,
            ]);
        }

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", [
                'fechaFin' => now()->toDateTimeString(),
            ])->assertOk()
            ->assertJsonPath('data.tEfectivo', '400.00')
            ->assertJsonPath('data.tTarjeta', '250.00');
    }

    public function test_cerrar_corte_con_cero_pagos_guarda_totales_en_cero(): void
    {
        $corte = Corte::factory()->abierto()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", [
                'fechaFin' => now()->toDateTimeString(),
            ])->assertOk()
            ->assertJsonPath('data.tEfectivo', '0.00')
            ->assertJsonPath('data.tTarjeta', '0.00');
    }

    public function test_no_se_puede_cerrar_corte_con_fecha_anterior_al_inicio(): void
    {
        $corte = Corte::factory()->abierto()->create(['fechaInicio' => now()]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", [
                'fechaFin' => now()->subDay()->toDateTimeString(),
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['fechaFin']);

        $this->assertNull($corte->fresh()->fechaFin);
    }

    public function test_cierre_rechaza_totales_manual_del_frontend(): void
    {
        $corte = Corte::factory()->abierto()->create();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", [
                'fechaFin' => now()->toDateTimeString(),
                'tEfectivo' => 999.00,
                'tTarjeta' => 999.00,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tEfectivo', 'tTarjeta']);

        $this->assertNull($corte->fresh()->fechaFin);
    }

    public function test_no_se_puede_cerrar_dos_veces_un_corte(): void
    {
        $corte = Corte::factory()->abierto()->create();
        $admin = $this->crearAdmin();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", ['fechaFin' => now()->toDateTimeString()])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", ['fechaFin' => now()->addHour()->toDateTimeString()])
            ->assertUnprocessable();
    }

    public function test_no_se_puede_modificar_corte_cerrado(): void
    {
        $corte = Corte::factory()->create([
            'fechaFin' => now(),
            'tEfectivo' => 400.00,
            'tTarjeta' => 250.00,
        ]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", ['fDeCaja' => 800.00])
            ->assertUnprocessable();

        $this->assertSame('400.00', $corte->fresh()->tEfectivo);
        $this->assertSame('250.00', $corte->fresh()->tTarjeta);
    }

    public function test_no_se_puede_desactivar_corte_cerrado(): void
    {
        $corte = Corte::factory()->create(['fechaFin' => now()]);

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->deleteJson("/api/cortes/{$corte->idCorte}")
            ->assertUnprocessable();

        $this->assertTrue($corte->fresh()->estado);
    }

    public function test_dentista_no_puede_acceder_a_cortes(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->getJson('/api/cortes')
            ->assertForbidden();
    }
}
