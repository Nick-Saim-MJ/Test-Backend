<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use App\Models\PlanDia;
use App\Models\User;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\PlanInscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class PlanTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $usuario;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear estructura básica
        $this->usuario = User::factory()->create();
        
        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
    }

    #[Test]
    public function puede_crear_plan_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => 'Aventura Completa en el Titicaca',
            'descripcion' => 'Experiencia única de 5 días explorando el lago navegable más alto del mundo',
            'que_incluye' => 'Transporte, hospedaje, comidas, guía especializado, actividades culturales',
            'capacidad' => 15,
            'duracion_dias' => 5,
            'es_publico' => true,
            'estado' => Plan::ESTADO_ACTIVO,
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio_total' => 850.00,
            'dificultad' => Plan::DIFICULTAD_MODERADO,
            'requerimientos' => 'Condición física básica, documentos de identidad',
            'que_llevar' => 'Ropa abrigada, protector solar, zapatos cómodos',
            'imagen_principal' => 'plans/plan123.jpg',
            'imagenes_galeria' => ['plans/gallery/img1.jpg', 'plans/gallery/img2.jpg']
        ];

        // Act
        $plan = Plan::create($data);

        // Assert
        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals($data['nombre'], $plan->nombre);
        $this->assertEquals($data['duracion_dias'], $plan->duracion_dias);
        $this->assertEquals($data['precio_total'], $plan->precio_total);
        $this->assertTrue($plan->es_publico);
        $this->assertEquals(Plan::ESTADO_ACTIVO, $plan->estado);
        
        $this->assertDatabaseHas('plans', [
            'nombre' => $data['nombre'],
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $plan = new Plan();
        $data = [
            'nombre' => 'Plan Test',
            'descripcion' => 'Descripción del plan',
            'capacidad' => 20,
            'duracion_dias' => 3,
            'es_publico' => true,
            'estado' => Plan::ESTADO_ACTIVO,
            'precio_total' => 500.00,
            'dificultad' => Plan::DIFICULTAD_FACIL,
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $plan->fill($data);

        // Assert
        $this->assertEquals('Plan Test', $plan->nombre);
        $this->assertEquals(20, $plan->capacidad);
        $this->assertEquals(3, $plan->duracion_dias);
        $this->assertTrue($plan->es_publico);
        $this->assertEquals(500.00, $plan->precio_total);
        $this->assertNull($plan->campo_no_permitido);
    }

    #[Test]
    public function casts_convierte_tipos_correctamente()
    {
        // Arrange & Act
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'es_publico' => '1', // String
            'precio_total' => '850.75', // String
            'imagenes_galeria' => ['img1.jpg', 'img2.jpg'] // Array
        ]);

        // Assert
        $this->assertIsBool($plan->es_publico);
        $this->assertTrue($plan->es_publico);
        $this->assertIsFloat($plan->precio_total);
        $this->assertEquals(850.75, $plan->precio_total);
        $this->assertIsArray($plan->imagenes_galeria);
        $this->assertEquals(['img1.jpg', 'img2.jpg'], $plan->imagenes_galeria);
    }

    #[Test]
    public function constantes_de_estado_estan_definidas()
    {
        // Assert
        $this->assertEquals('activo', Plan::ESTADO_ACTIVO);
        $this->assertEquals('inactivo', Plan::ESTADO_INACTIVO);
        $this->assertEquals('borrador', Plan::ESTADO_BORRADOR);
    }

    #[Test]
    public function constantes_de_dificultad_estan_definidas()
    {
        // Assert
        $this->assertEquals('facil', Plan::DIFICULTAD_FACIL);
        $this->assertEquals('moderado', Plan::DIFICULTAD_MODERADO);
        $this->assertEquals('dificil', Plan::DIFICULTAD_DIFICIL);
    }

    #[Test]
    public function relacion_creado_por_funciona_correctamente()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $usuarioCreador = $plan->creadoPor;

        // Assert
        $this->assertInstanceOf(User::class, $usuarioCreador);
        $this->assertEquals($this->usuario->id, $usuarioCreador->id);
        $this->assertEquals($this->usuario->name, $usuarioCreador->name);
    }

    #[Test]
    public function relacion_emprendedor_legacy_funciona_correctamente()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $emprendedorRelacionado = $plan->emprendedor;

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $emprendedorRelacionado);
        $this->assertEquals($this->emprendedor->id, $emprendedorRelacionado->id);
        $this->assertEquals($this->emprendedor->nombre, $emprendedorRelacionado->nombre);
    }

    #[Test]
    public function relacion_dias_funciona_correctamente()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'duracion_dias' => 3
        ]);

        $dias = PlanDia::factory()->count(3)->create([
            'plan_id' => $plan->id,
            'numero_dia' => function ($attributes, $key) {
                return $key + 1; // Día 1, 2, 3
            }
        ]);

        // Act
        $diasRelacionados = $plan->dias;

        // Assert
        $this->assertCount(3, $diasRelacionados);
        foreach ($dias as $dia) {
            $this->assertTrue(
                $diasRelacionados->contains('id', $dia->id)
            );
        }
        
        // Verificar ordenamiento por numero_dia
        $numerosOrdenados = $diasRelacionados->pluck('numero_dia')->toArray();
        $this->assertEquals([1, 2, 3], $numerosOrdenados);
    }

    #[Test]
    public function puede_verificar_cupos_disponibles()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 20
        ]);

        // Sin inscripciones
        $this->assertTrue($plan->tieneCuposDisponibles());
        $this->assertEquals(20, $plan->cupos_disponibles);

        // Crear algunas inscripciones confirmadas
        PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $this->usuario->id,
            'estado' => 'confirmada',
            'numero_participantes' => 8
        ]);

        PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => User::factory()->create()->id,
            'estado' => 'confirmada',
            'numero_participantes' => 5
        ]);

        // Refrescar el modelo para obtener datos actualizados
        $plan->refresh();

        // Act & Assert
        $this->assertTrue($plan->tieneCuposDisponibles());
        $this->assertEquals(7, $plan->cupos_disponibles); // 20 - 8 - 5 = 7
    }

    #[Test]
    public function no_tiene_cupos_cuando_esta_lleno()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 10
        ]);

        PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $this->usuario->id,
            'estado' => 'confirmada',
            'numero_participantes' => 10
        ]);

        $plan->refresh();

        // Act & Assert
        $this->assertFalse($plan->tieneCuposDisponibles());
        $this->assertEquals(0, $plan->cupos_disponibles);
    }

    #[Test]
    public function inscripciones_pendientes_no_afectan_cupos_disponibles()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'capacidad' => 15
        ]);

        PlanInscripcion::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $this->usuario->id,
            'estado' => 'pendiente', // Estado pendiente
            'numero_participantes' => 10
        ]);

        $plan->refresh();

        // Act & Assert
        $this->assertTrue($plan->tieneCuposDisponibles());
        $this->assertEquals(15, $plan->cupos_disponibles); // No debe restar las pendientes
    }

    #[Test]
    public function puede_obtener_imagen_principal_url()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'imagen_principal' => 'plans/test-image.jpg'
        ]);

        // Act
        $url = $plan->imagen_principal_url;

        // Assert
        $this->assertNotNull($url);
        $this->assertStringContains('plans/test-image.jpg', $url);
    }

    #[Test]
    public function retorna_null_si_no_hay_imagen_principal()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'imagen_principal' => null
        ]);

        // Act
        $url = $plan->imagen_principal_url;

        // Assert
        $this->assertNull($url);
    }

    #[Test]
    public function puede_obtener_imagenes_galeria_urls()
    {
        // Arrange
        $imagenes = ['plans/gallery/img1.jpg', 'plans/gallery/img2.jpg'];
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'imagenes_galeria' => $imagenes
        ]);

        // Act
        $urls = $plan->imagenes_galeria_urls;

        // Assert
        $this->assertIsArray($urls);
        $this->assertCount(2, $urls);
        foreach ($urls as $url) {
            $this->assertIsString($url);
        }
    }

    #[Test]
    public function retorna_array_vacio_si_no_hay_imagenes_galeria()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'imagenes_galeria' => null
        ]);

        // Act
        $urls = $plan->imagenes_galeria_urls;

        // Assert
        $this->assertIsArray($urls);
        $this->assertEmpty($urls);
    }

    #[Test]
    public function scope_activos_filtra_correctamente()
    {
        // Arrange
        $planActivo = Plan::factory()->activo()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        $planInactivo = Plan::factory()->inactivo()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $planesActivos = Plan::activos()->get();

        // Assert
        $this->assertTrue($planesActivos->contains('id', $planActivo->id));
        $this->assertFalse($planesActivos->contains('id', $planInactivo->id));
    }

    #[Test]
    public function scope_publicos_filtra_correctamente()
    {
        // Arrange
        $planPublico = Plan::factory()->publico()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        $planPrivado = Plan::factory()->privado()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $planesPublicos = Plan::publicos()->get();

        // Assert
        $this->assertTrue($planesPublicos->contains('id', $planPublico->id));
        $this->assertFalse($planesPublicos->contains('id', $planPrivado->id));
    }

    #[Test]
    public function scope_por_dificultad_filtra_correctamente()
    {
        // Arrange
        $planFacil = Plan::factory()->facil()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
        
        $planDificil = Plan::factory()->dificil()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $planesFaciles = Plan::porDificultad(Plan::DIFICULTAD_FACIL)->get();

        // Assert
        $this->assertTrue($planesFaciles->contains('id', $planFacil->id));
        $this->assertFalse($planesFaciles->contains('id', $planDificil->id));
    }

    #[Test]
    public function scope_buscar_encuentra_por_nombre()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'nombre' => 'Aventura en el Titicaca'
        ]);

        // Act
        $resultados = Plan::buscar('Titicaca')->get();

        // Assert
        $this->assertTrue($resultados->contains('id', $plan->id));
    }

    #[Test]
    public function scope_buscar_encuentra_por_descripcion()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'descripcion' => 'Experiencia única navegando por el lago sagrado'
        ]);

        // Act
        $resultados = Plan::buscar('lago sagrado')->get();

        // Assert
        $this->assertTrue($resultados->contains('id', $plan->id));
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'nombre' => 'Nombre Original',
            'precio_total' => 500.00
        ]);

        // Act
        $plan->update([
            'nombre' => 'Nombre Actualizado',
            'precio_total' => 750.00,
            'estado' => Plan::ESTADO_INACTIVO
        ]);

        // Assert
        $this->assertEquals('Nombre Actualizado', $plan->fresh()->nombre);
        $this->assertEquals(750.00, $plan->fresh()->precio_total);
        $this->assertEquals(Plan::ESTADO_INACTIVO, $plan->fresh()->estado);
    }

    #[Test]
    public function puede_eliminar_plan()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $id = $plan->id;

        // Act
        $result = $plan->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('plans', ['id' => $id]);
    }

    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Assert
        $this->assertNotNull($plan->created_at);
        $this->assertNotNull($plan->updated_at);
        $this->assertInstanceOf(Carbon::class, $plan->created_at);
        $this->assertInstanceOf(Carbon::class, $plan->updated_at);
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $array = $plan->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
        $this->assertArrayHasKey('descripcion', $array);
        $this->assertArrayHasKey('capacidad', $array);
        $this->assertArrayHasKey('duracion_dias', $array);
        $this->assertArrayHasKey('precio_total', $array);
        $this->assertArrayHasKey('dificultad', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        
        // Verificar atributos appended
        $this->assertArrayHasKey('imagen_principal_url', $array);
        $this->assertArrayHasKey('imagenes_galeria_urls', $array);
        $this->assertArrayHasKey('cupos_disponibles', $array);
    }

    #[Test]
    public function maneja_valores_nulos_correctamente()
    {
        // Arrange & Act
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id,
            'descripcion' => null,
            'requerimientos' => null,
            'que_llevar' => null,
            'imagen_principal' => null,
            'imagenes_galeria' => null
        ]);

        // Assert
        $this->assertNull($plan->descripcion);
        $this->assertNull($plan->requerimientos);
        $this->assertNull($plan->que_llevar);
        $this->assertNull($plan->imagen_principal);
        $this->assertNull($plan->imagen_principal_url);
        $this->assertEquals([], $plan->imagenes_galeria_urls);
    }

    #[Test]
    public function puede_cargar_relaciones_eager()
    {
        // Arrange
        $plan = Plan::factory()->create([
            'creado_por_usuario_id' => $this->usuario->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        PlanDia::factory()->count(2)->create(['plan_id' => $plan->id]);

        // Act
        $planConRelaciones = Plan::with(['creadoPor', 'emprendedor', 'dias'])->find($plan->id);

        // Assert
        $this->assertTrue($planConRelaciones->relationLoaded('creadoPor'));
        $this->assertTrue($planConRelaciones->relationLoaded('emprendedor'));
        $this->assertTrue($planConRelaciones->relationLoaded('dias'));
        $this->assertInstanceOf(User::class, $planConRelaciones->creadoPor);
        $this->assertInstanceOf(Emprendedor::class, $planConRelaciones->emprendedor);
        $this->assertCount(2, $planConRelaciones->dias);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $plan = new Plan();

        // Act
        $primaryKey = $plan->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $plan = new Plan();

        // Act
        $timestamps = $plan->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }
}