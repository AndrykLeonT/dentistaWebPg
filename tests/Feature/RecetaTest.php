<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Receta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class RecetaTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    public function test_dentista_puede_crear_receta(): void
    {
        $cita = Cita::factory()->create(['estado' => true]);

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->postJson('/api/recetas', [
                'idCita'       => $cita->idCita,
                'indicaciones' => 'Tomar ibuprofeno 400mg cada 8 horas.',
            ])->assertCreated();
    }

    public function test_admin_puede_crear_receta(): void
    {
        $cita = Cita::factory()->create(['estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/recetas', [
                'idCita'       => $cita->idCita,
                'indicaciones' => 'Reposo absoluto por 3 días.',
            ])->assertCreated();
    }

    public function test_recepcionista_no_puede_crear_receta(): void
    {
        $cita = Cita::factory()->create(['estado' => true]);

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/recetas', [
                'idCita'       => $cita->idCita,
                'indicaciones' => 'Test.',
            ])->assertForbidden();
    }

    public function test_recepcionista_no_puede_ver_recetas(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->getJson('/api/recetas')
            ->assertForbidden();
    }

    public function test_no_se_pueden_crear_dos_recetas_para_la_misma_cita(): void
    {
        $cita = Cita::factory()->create(['estado' => true]);
        Receta::factory()->create(['idCita' => $cita->idCita, 'estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/recetas', [
                'idCita'       => $cita->idCita,
                'indicaciones' => 'Segunda receta — no debe permitirse.',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['idCita']);
    }

    public function test_solo_admin_puede_eliminar_receta(): void
    {
        $receta = Receta::factory()->create(['estado' => true]);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->deleteJson("/api/recetas/{$receta->idReceta}")
            ->assertNoContent();

        $this->assertFalse((bool) $receta->fresh()->estado);
    }

    public function test_dentista_no_puede_eliminar_receta(): void
    {
        $receta = Receta::factory()->create(['estado' => true]);

        $this->actingAs($this->crearDentista(), 'sanctum')
            ->deleteJson("/api/recetas/{$receta->idReceta}")
            ->assertForbidden();
    }
}
