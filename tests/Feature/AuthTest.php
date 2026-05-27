<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\TipoEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesEmployees;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesEmployees;

    private function olvidarAutenticacionResuelta(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function empleadoConPalabraClave(string $palabraClave = 'ClaveAdmin123!'): Empleado
    {
        $empleado = $this->crearAdmin();
        $empleado->update([
            'palabraClave' => Hash::make($palabraClave),
            'cambioContraseña' => true,
        ]);

        return $empleado;
    }

    public function test_login_con_credenciales_validas_retorna_token(): void
    {
        $empleado = $this->crearAdmin();

        $response = $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'requiresPasswordChange', 'empleado'])
            ->assertJsonPath('requiresPasswordChange', false);
    }

    public function test_login_con_contrasena_incorrecta_retorna_401(): void
    {
        $empleado = $this->crearAdmin();

        $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'incorrecta',
        ])->assertUnauthorized();
    }

    public function test_login_de_empleado_inactivo_retorna_401(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->update(['estado' => false]);

        $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_token_previo_de_empleado_desactivado_ya_no_permite_acceso(): void
    {
        $empleado = $this->crearRecepcionista();
        $token = $empleado->createToken('sesion-empleado')->plainTextToken;
        $adminToken = $this->crearAdmin()->createToken('sesion-admin')->plainTextToken;

        $this->withToken($adminToken)
            ->deleteJson("/api/empleados/{$empleado->idEmpleado}")
            ->assertNoContent();

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_empleado_desactivado_no_puede_ejecutar_operaciones_de_su_rol(): void
    {
        $empleado = $this->crearRecepcionista();
        $token = $empleado->createToken('sesion-empleado')->plainTextToken;
        $adminToken = $this->crearAdmin()->createToken('sesion-admin')->plainTextToken;

        $this->withToken($adminToken)
            ->deleteJson("/api/empleados/{$empleado->idEmpleado}")
            ->assertNoContent();

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->postJson('/api/personas', [
                'nombre'    => 'Paciente',
                'apellidoP' => 'Bloqueado',
                'celular'   => '6120000000',
            ])->assertUnauthorized();
    }

    public function test_middleware_revoca_token_si_empleado_esta_inactivo(): void
    {
        $empleado = $this->crearRecepcionista();
        $token = $empleado->createToken('sesion-activa')->plainTextToken;

        $empleado->update(['estado' => false]);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->assertCount(0, $empleado->tokens()->get());
    }

    public function test_token_expirado_no_permite_acceso(): void
    {
        $empleado = $this->crearAdmin();
        $token = $empleado->createToken('sesion-expirada')->plainTextToken;

        $empleado->tokens()->first()->forceFill([
            'created_at' => now()->subMinutes(config('sanctum.expiration') + 1),
        ])->save();

        $this->assertNotNull(config('sanctum.expiration'));

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_login_con_cambio_de_contrasena_pendiente_retorna_flag(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->update(['cambioContraseña' => true]);

        $response = $this->postJson('/api/login', [
            'usuario'    => $empleado->usuario,
            'contraseña' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('requiresPasswordChange', true);
    }

    public function test_logout_elimina_el_token_actual(): void
    {
        $empleado = $this->crearAdmin();
        $token    = $empleado->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada.');

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_me_retorna_datos_del_empleado_autenticado(): void
    {
        $empleado = $this->crearAdmin();

        $response = $this->actingAs($empleado, 'sanctum')
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.usuario', $empleado->usuario);
    }

    public function test_change_password_actualiza_la_contrasena(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->update(['cambioContraseña' => true]);

        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/change-password', [
                'contraseñaActual'              => 'password123',
                'nuevaContraseña'               => 'nueva_pass_456',
                'nuevaContraseña_confirmation'  => 'nueva_pass_456',
            ])->assertOk();

        $this->assertFalse($empleado->fresh()->cambioContraseña);
    }

    public function test_change_password_falla_con_contrasena_actual_incorrecta(): void
    {
        $empleado = $this->crearAdmin();

        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/change-password', [
                'contraseñaActual'              => 'incorrecta',
                'nuevaContraseña'               => 'nueva_pass_456',
                'nuevaContraseña_confirmation'  => 'nueva_pass_456',
            ])->assertUnprocessable();
    }

    public function test_recover_password_keyword_actualiza_contrasena_y_permite_login(): void
    {
        $empleado = $this->empleadoConPalabraClave();

        $this->postJson('/api/recover-password-keyword', [
            'usuario' => $empleado->usuario,
            'palabraClave' => 'ClaveAdmin123!',
            'new_password' => 'NuevaPassword123!',
            'new_password_confirmation' => 'NuevaPassword123!',
        ])->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada correctamente.');

        $empleado->refresh();
        $this->assertTrue(Hash::check('NuevaPassword123!', $empleado->contraseña));
        $this->assertFalse($empleado->cambioContraseña);

        $this->postJson('/api/login', [
            'usuario' => $empleado->usuario,
            'contraseña' => 'password123',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'usuario' => $empleado->usuario,
            'contraseña' => 'NuevaPassword123!',
        ])->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_recover_password_keyword_usuario_inexistente_retorna_404(): void
    {
        $this->postJson('/api/recover-password-keyword', [
            'usuario' => 'no-existe',
            'palabraClave' => 'ClaveAdmin123!',
            'new_password' => 'NuevaPassword123!',
            'new_password_confirmation' => 'NuevaPassword123!',
        ])->assertNotFound();
    }

    public function test_recover_password_keyword_palabra_clave_incorrecta_retorna_401(): void
    {
        $empleado = $this->empleadoConPalabraClave();

        $this->postJson('/api/recover-password-keyword', [
            'usuario' => $empleado->usuario,
            'palabraClave' => 'incorrecta',
            'new_password' => 'NuevaPassword123!',
            'new_password_confirmation' => 'NuevaPassword123!',
        ])->assertUnauthorized();
    }

    public function test_recover_password_keyword_valida_payload(): void
    {
        $this->postJson('/api/recover-password-keyword', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'usuario',
                'palabraClave',
                'new_password',
                'new_password_confirmation',
            ]);

        $this->postJson('/api/recover-password-keyword', [
            'usuario' => 'usuario',
            'palabraClave' => 'ClaveAdmin123!',
            'new_password' => 'NuevaPassword123!',
            'new_password_confirmation' => 'OtraPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);

        $this->postJson('/api/recover-password-keyword', [
            'usuario' => 'usuario',
            'palabraClave' => 'ClaveAdmin123!',
            'new_password' => 'corta',
            'new_password_confirmation' => 'corta',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_recover_password_keyword_revoca_tokens_previos(): void
    {
        $empleado = $this->empleadoConPalabraClave();
        $token = $empleado->createToken('sesion-previa')->plainTextToken;

        $this->postJson('/api/recover-password-keyword', [
            'usuario' => $empleado->usuario,
            'palabraClave' => 'ClaveAdmin123!',
            'new_password' => 'NuevaPassword123!',
            'new_password_confirmation' => 'NuevaPassword123!',
        ])->assertOk();

        $this->olvidarAutenticacionResuelta();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->assertCount(0, $empleado->tokens()->get());
    }

    public function test_recover_password_keyword_migra_palabra_clave_legacy_en_texto_plano(): void
    {
        $empleado = $this->crearAdmin();
        $empleado->forceFill(['palabraClave' => 'legacy-key'])->save();

        $this->postJson('/api/recover-password-keyword', [
            'usuario' => $empleado->usuario,
            'palabraClave' => 'legacy-key',
            'new_password' => 'NuevaPassword123!',
            'new_password_confirmation' => 'NuevaPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('legacy-key', $empleado->fresh()->palabraClave));
    }

    public function test_endpoints_protegidos_requieren_autenticacion(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
