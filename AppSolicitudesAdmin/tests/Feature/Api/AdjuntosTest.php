<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Models\Adjunto;
use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdjuntosTest extends TestCase
{
    use RefreshDatabase;

    private User $estudiante;

    private Solicitud $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->estudiante = User::factory()->estudiante()->create();
        $this->solicitud = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
    }

    private function contenidoPdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }

    private function pdf(string $nombre = 'doc.pdf'): UploadedFile
    {
        return $this->real($nombre, $this->contenidoPdf());
    }

    /**
     * Archivo real en disco: UploadedFile::fake() deduce el mime del nombre,
     * y estas pruebas necesitan que se detecte por el contenido.
     */
    private function real(string $nombre, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'adj');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    private function subir(UploadedFile $archivo, ?Solicitud $solicitud = null)
    {
        return $this->postJson('/api/v1/solicitudes/'.($solicitud ?? $this->solicitud)->id.'/adjuntos', ['archivo' => $archivo]);
    }

    public function test_el_estudiante_dueno_sube_una_imagen(): void
    {
        Sanctum::actingAs($this->estudiante);

        $r = $this->subir(UploadedFile::fake()->image('foto-original.jpg'))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'solicitud_id', 'nombre', 'tipo_archivo', 'subido_por' => ['id', 'nombre'], 'url', 'created_at']])
            ->assertJsonPath('data.tipo_archivo', 'image/jpeg')
            ->assertJsonPath('data.solicitud_id', $this->solicitud->id)
            ->assertJsonPath('data.subido_por.id', $this->estudiante->id);

        $adjunto = Adjunto::firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.jpg$/', $r->json('data.nombre'));
        $this->assertStringNotContainsString('foto-original', $adjunto->url_archivo);
        $this->assertSame(route('adjuntos.archivo', $adjunto->id), $r->json('data.url'));
        Storage::disk('local')->assertExists($adjunto->url_archivo);
        $this->assertSame('image/jpeg', $adjunto->tipo_archivo);
    }

    public function test_se_aceptan_png_y_pdf_con_extension_derivada_del_contenido(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->subir(UploadedFile::fake()->image('a.png'))->assertCreated()->assertJsonPath('data.tipo_archivo', 'image/png');
        $this->subir($this->pdf('informe.pdf'))->assertCreated()->assertJsonPath('data.tipo_archivo', 'application/pdf');

        $this->assertStringEndsWith('.png', Adjunto::orderBy('id')->first()->url_archivo);
        $this->assertStringEndsWith('.pdf', Adjunto::orderByDesc('id')->first()->url_archivo);
    }

    public function test_un_pdf_renombrado_como_jpg_se_guarda_con_su_extension_real(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->subir($this->real('truco.jpg', $this->contenidoPdf()))
            ->assertCreated()
            ->assertJsonPath('data.tipo_archivo', 'application/pdf');

        $this->assertStringEndsWith('.pdf', Adjunto::firstOrFail()->url_archivo);
    }

    public function test_tipo_no_permitido_da_422(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->subir($this->real('nota.txt', 'hola mundo'))
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->assertSame(0, Adjunto::count());
    }

    public function test_archivo_con_extension_jpg_pero_contenido_no_imagen_da_422(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->subir($this->real('falso.jpg', 'esto no es una imagen'))
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    public function test_mas_de_5_mb_da_422_y_5_mb_exactos_pasan(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->subir(UploadedFile::fake()->image('grande.jpg')->size(5121))
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->subir(UploadedFile::fake()->image('justo.jpg')->size(5120))->assertCreated();
    }

    public function test_falta_el_archivo_da_422(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->postJson('/api/v1/solicitudes/'.$this->solicitud->id.'/adjuntos', [])
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    public function test_el_sexto_adjunto_da_422(): void
    {
        Sanctum::actingAs($this->estudiante);
        Adjunto::factory()->count(5)->create(['solicitud_id' => $this->solicitud->id, 'subido_por' => $this->estudiante->id]);

        $this->subir(UploadedFile::fake()->image('sexta.jpg'))
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->assertSame(5, Adjunto::count());
    }

    public function test_solicitud_cerrada_o_cancelada_no_admite_adjuntos(): void
    {
        Sanctum::actingAs($this->estudiante);

        foreach ([EstadoNombre::Cerrada, EstadoNombre::Cancelada] as $estado) {
            $solicitud = Solicitud::factory()->conEstado($estado)->create(['estudiante_id' => $this->estudiante->id]);
            $this->subir(UploadedFile::fake()->image('a.jpg'), $solicitud)
                ->assertUnprocessable()->assertJsonValidationErrors('archivo');
        }
        $this->assertSame(0, Adjunto::count());
    }

    public function test_estados_abiertos_admiten_adjuntos(): void
    {
        Sanctum::actingAs($this->estudiante);

        foreach ([EstadoNombre::Pendiente, EstadoNombre::Asignada, EstadoNombre::EnProceso] as $estado) {
            $solicitud = Solicitud::factory()->conEstado($estado)->create(['estudiante_id' => $this->estudiante->id]);
            $this->subir(UploadedFile::fake()->image('a.jpg'), $solicitud)->assertCreated();
        }
    }

    public function test_otro_estudiante_no_puede_subir(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->subir(UploadedFile::fake()->image('a.jpg'))
            ->assertForbidden()->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
        $this->assertSame(0, Adjunto::count());
    }

    public function test_responsable_y_administrador_no_pueden_subir(): void
    {
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id]);

        foreach ([$responsable, User::factory()->administrador()->create(), User::factory()->personalAdministrativo()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->subir(UploadedFile::fake()->image('a.jpg'))->assertForbidden();
        }
        $this->assertSame(0, Adjunto::count());
    }

    public function test_subir_sin_token_da_401_y_a_solicitud_inexistente_404(): void
    {
        $this->subir(UploadedFile::fake()->image('a.jpg'))->assertUnauthorized();

        Sanctum::actingAs($this->estudiante);
        $this->postJson('/api/v1/solicitudes/999999/adjuntos', ['archivo' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
    }

    public function test_listado_de_adjuntos_por_quienes_ven_la_solicitud(): void
    {
        $a = Adjunto::factory()->create(['solicitud_id' => $this->solicitud->id, 'subido_por' => $this->estudiante->id]);
        $b = Adjunto::factory()->create(['solicitud_id' => $this->solicitud->id, 'subido_por' => $this->estudiante->id]);
        Adjunto::factory()->create();

        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id]);

        foreach ([$this->estudiante, $responsable, User::factory()->administrador()->create(), User::factory()->personalAdministrativo()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/solicitudes/'.$this->solicitud->id.'/adjuntos')
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.id', $a->id)
                ->assertJsonPath('data.1.id', $b->id);
        }
    }

    public function test_listado_denegado_a_ajenos_y_a_responsable_no_asignado(): void
    {
        foreach ([User::factory()->estudiante()->create(), User::factory()->responsable()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/solicitudes/'.$this->solicitud->id.'/adjuntos')->assertForbidden();
        }
    }

    public function test_listado_401_y_404(): void
    {
        $this->getJson('/api/v1/solicitudes/'.$this->solicitud->id.'/adjuntos')->assertUnauthorized();

        Sanctum::actingAs($this->estudiante);
        $this->getJson('/api/v1/solicitudes/999999/adjuntos')->assertNotFound();
    }

    public function test_listar_20_adjuntos_no_hace_mas_consultas_que_listar_2(): void
    {
        Sanctum::actingAs($this->estudiante);
        $contar = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/solicitudes/'.$this->solicitud->id.'/adjuntos')->assertOk();
            $n = count(DB::getQueryLog());
            DB::flushQueryLog();

            return $n;
        };

        Adjunto::factory()->count(2)->create(['solicitud_id' => $this->solicitud->id]);
        $con2 = $contar();
        Adjunto::factory()->count(18)->create(['solicitud_id' => $this->solicitud->id]);
        $con20 = $contar();

        $this->assertLessThanOrEqual($con2, $con20);
    }

    public function test_descarga_devuelve_el_binario_con_cabeceras_correctas(): void
    {
        Sanctum::actingAs($this->estudiante);
        $id = $this->subir($this->pdf())->json('data.id');

        $r = $this->get('/api/v1/adjuntos/'.$id.'/archivo', ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline; filename=', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString(basename(Adjunto::find($id)->url_archivo), $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $r->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-1.4', $r->streamedContent());
    }

    public function test_descarga_permitida_a_admin_y_responsable_asignado(): void
    {
        Sanctum::actingAs($this->estudiante);
        $id = $this->subir(UploadedFile::fake()->image('a.png'))->json('data.id');
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id]);

        foreach ([$responsable, User::factory()->administrador()->create(), User::factory()->personalAdministrativo()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->get('/api/v1/adjuntos/'.$id.'/archivo')->assertOk();
        }
    }

    public function test_descarga_denegada_a_usuarios_sin_acceso(): void
    {
        $adjunto = Adjunto::factory()->create(['solicitud_id' => $this->solicitud->id]);
        Storage::disk('local')->put($adjunto->url_archivo, 'x');

        foreach ([User::factory()->estudiante()->create(), User::factory()->responsable()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/adjuntos/'.$adjunto->id.'/archivo')->assertForbidden();
        }
    }

    public function test_descarga_401_sin_token_y_404_si_no_existe_o_falta_en_disco(): void
    {
        $adjunto = Adjunto::factory()->create(['solicitud_id' => $this->solicitud->id]);
        $this->getJson('/api/v1/adjuntos/'.$adjunto->id.'/archivo')->assertUnauthorized();

        Sanctum::actingAs($this->estudiante);
        $this->getJson('/api/v1/adjuntos/999999/archivo')->assertNotFound();
        $this->getJson('/api/v1/adjuntos/'.$adjunto->id.'/archivo')
            ->assertNotFound()->assertJsonPath('message', 'Recurso no encontrado.');
    }

    public function test_ninguna_respuesta_expone_la_ruta_interna_del_disco(): void
    {
        Sanctum::actingAs($this->estudiante);
        $creado = $this->subir(UploadedFile::fake()->image('a.jpg'));
        $listado = $this->getJson('/api/v1/solicitudes/'.$this->solicitud->id.'/adjuntos');
        $ruta = Adjunto::firstOrFail()->url_archivo;

        foreach ([$creado, $listado] as $respuesta) {
            $this->assertStringNotContainsString($ruta, $respuesta->getContent());
            $this->assertStringNotContainsString(str_replace('/', '\/', $ruta), $respuesta->getContent());
            $this->assertStringNotContainsString('storage', $respuesta->getContent());
            $this->assertArrayNotHasKey('url_archivo', $respuesta->json('data.0') ?? $respuesta->json('data'));
        }
    }

    public function test_el_archivo_se_guarda_en_el_disco_privado_y_no_en_el_publico(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->estudiante);
        $this->subir(UploadedFile::fake()->image('a.jpg'))->assertCreated();

        Storage::disk('local')->assertExists(Adjunto::firstOrFail()->url_archivo);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
