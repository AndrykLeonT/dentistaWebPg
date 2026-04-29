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

    public function test_cerrar_corte_calcula_totales_de_pagos(): void
    {
        $corte   = Corte::factory()->abierto()->create();
        $persona = Persona::factory()->create();
        $admin   = $this->crearAdmin();

        Pago::factory()->create([
            'idCorte'   => $corte->idCorte,
            'idPersona' => $persona->idPersona,
            'idEmpleado'=> $admin->idEmpleado,
            'efectivo'  => 200.00,
            'tarjeta'   => 300.00,
            'estado'    => true,
        ]);
        Pago::factory()->create([
            'idCorte'   => $corte->idCorte,
            'idPersona' => $persona->idPersona,
            'idEmpleado'=> $admin->idEmpleado,
            'efectivo'  => 100.00,
            'tarjeta'   => 150.00,
            'estado'    => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", [
                'fechaFin' => now()->toDateTimeString(),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tEfectivo', '300.00')
            ->assertJsonPath('data.tTarjeta', '450.00');
    }

    public function test_cerrar_corte_con_cero_pagos_guarda_totales_en_cero(): void
    {
        $corte = Corte::factory()->abierto()->create();

        $response = $this->actingAs($this->crearAdmin(), 'sanctum')
            ->putJson("/api/cortes/{$corte->idCorte}", [
                'fechaFin' => now()->toDateTimeString(),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tEfectivo', '0.00')
            ->assertJsonPath('data.tTarjeta', '0.00');
    }

    public function test_dentista_no_puede_acceder_a_cortes(): void
    {
        $this->actingAs($this->crearDentista(), 'sanctum')
            ->getJson('/api/cortes')
            ->assertForbidden();
    }
}
