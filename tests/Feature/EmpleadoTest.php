<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\TipoEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class EmpleadoTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function payloadNuevoEmpleado(array $overrides = []): array
    {
        $tipo = TipoEmpleado::factory()->dentista()->create();

        return array_merge([
            'nombre'            => 'Laura',
            'apellidoP'         => 'Mendoza',
            'apellidoM'         => 'Cruz',
            'celular'           => '6121112233',
            'correoElectronico' => 'laura@clinica.com',
            'idTipoEmpleado'    => $tipo->idTipoEmpleado,
            'usuario'           => 'lmendoza',
            'contraseña'        => 'password123',
            'palabraClave'      => 'primavera',
        ], $overrides);
    }

    public function test_admin_puede_crear_empleado(): void
    {
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/empleados', $this->payloadNuevoEmpleado())
            ->assertCreated()
            ->assertJsonPath('data.usuario', 'lmendoza');
    }

    public function test_empleado_creado_tiene_cambio_contraseña_en_false(): void
    {
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/empleados', $this->payloadNuevoEmpleado());

        $empleado = Empleado::where('usuario', 'lmendoza')->first();
        $this->assertFalse($empleado->cambioContraseña);
    }

    public function test_contraseña_se_guarda_hasheada(): void
    {
        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/empleados', $this->payloadNuevoEmpleado());

        $empleado = Empleado::where('usuario', 'lmendoza')->first();
        $this->assertTrue(Hash::check('password123', $empleado->contraseña));
    }

    public function test_recepcionista_no_puede_crear_empleado(): void
    {
        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson('/api/empleados', $this->payloadNuevoEmpleado())
            ->assertForbidden();
    }

    public function test_admin_puede_resetear_contraseña(): void
    {
        $empleado = $this->crearDentista();
        $empleado->createToken('sesion-previa');

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/empleados/{$empleado->idEmpleado}/reset-password", [
                'nuevaContraseña'              => 'nueva_temp_123',
                'nuevaContraseña_confirmation' => 'nueva_temp_123',
            ])->assertOk();

        $empleado->refresh();
        $this->assertTrue($empleado->cambioContraseña);
        $this->assertTrue(Hash::check('nueva_temp_123', $empleado->contraseña));
        $this->assertCount(0, $empleado->tokens()->get());
    }

    public function test_reset_password_activa_flag_cambio_contraseña(): void
    {
        $empleado = $this->crearRecepcionista();
        $this->assertFalse($empleado->cambioContraseña);

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson("/api/empleados/{$empleado->idEmpleado}/reset-password", [
                'nuevaContraseña'              => 'reset_pass_456',
                'nuevaContraseña_confirmation' => 'reset_pass_456',
            ])->assertOk();

        $this->assertTrue($empleado->fresh()->cambioContraseña);
    }

    public function test_recepcionista_no_puede_resetear_contraseña(): void
    {
        $objetivo = $this->crearDentista();

        $this->actingAs($this->crearRecepcionista(), 'sanctum')
            ->postJson("/api/empleados/{$objetivo->idEmpleado}/reset-password", [
                'nuevaContraseña'              => 'intento_hack',
                'nuevaContraseña_confirmation' => 'intento_hack',
            ])->assertForbidden();
    }

    public function test_no_se_puede_crear_empleado_con_usuario_duplicado(): void
    {
        $existente = $this->crearAdmin();

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->postJson('/api/empleados', $this->payloadNuevoEmpleado(['usuario' => $existente->usuario]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario']);
    }

    public function test_destroy_desactiva_empleado(): void
    {
        $empleado = $this->crearDentista();
        $empleado->createToken('sesion-uno');
        $empleado->createToken('sesion-dos');

        $this->actingAs($this->crearAdmin(), 'sanctum')
            ->deleteJson("/api/empleados/{$empleado->idEmpleado}")
            ->assertNoContent();

        $this->assertFalse((bool) $empleado->fresh()->estado);
        $this->assertCount(0, $empleado->tokens()->get());
    }
}
