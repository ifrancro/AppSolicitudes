<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_devuelve_token_y_usuario_con_rol_como_objeto(): void
    {
        $usuario = User::factory()->responsable()->create([
            'email' => 'ana@campus.test',
            'password_hash' => 'secreto123',
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@campus.test', 'password' => 'secreto123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'nombre', 'email', 'rol' => ['id', 'nombre']]])
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('usuario.rol.nombre', 'responsable')
            ->assertJsonMissingPath('usuario.password_hash');

        $this->assertSame(1, $usuario->tokens()->count());
    }

    public function test_el_token_devuelto_autentica_las_peticiones(): void
    {
        User::factory()->create(['email' => 'ana@campus.test', 'password_hash' => 'secreto123']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'ana@campus.test', 'password' => 'secreto123'])
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@campus.test')
            ->assertJsonPath('data.rol.nombre', 'estudiante');
    }

    public function test_login_con_contrasena_incorrecta_da_422_y_no_401(): void
    {
        User::factory()->create(['email' => 'ana@campus.test', 'password_hash' => 'secreto123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@campus.test', 'password' => 'otra'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
    }

    public function test_login_con_correo_inexistente_responde_igual_que_contrasena_incorrecta(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nadie@campus.test', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
    }

    public function test_login_valida_los_campos_obligatorios(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.');
    }

    public function test_login_de_usuario_desactivado_da_403(): void
    {
        User::factory()->inactivo()->create(['email' => 'ana@campus.test', 'password_hash' => 'secreto123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@campus.test', 'password' => 'secreto123'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu cuenta está desactivada.');
    }

    public function test_login_se_limita_a_cinco_intentos_por_minuto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ana@campus.test', 'password' => 'mal'])
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@campus.test', 'password' => 'mal'])
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_me_sin_token_da_401_con_mensaje(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No autenticado.']);
    }

    public function test_me_con_token_invalido_da_401(): void
    {
        $this->withToken('1|token-inventado')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_revoca_el_token(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('movil')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $usuario->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_usuario_desactivado_despues_del_login_pierde_el_acceso_con_401(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('movil')->plainTextToken;
        $usuario->update(['activo' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Tu cuenta está desactivada.');

        $this->assertSame(0, $usuario->tokens()->count());
    }

    public function test_rutas_inexistentes_y_recursos_ausentes_dan_404_json_sin_filtrar_el_modelo(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso no encontrado.']);
    }
}
