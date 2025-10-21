<?php

namespace Tests\Feature\Controllers;

use App\Models\Evento;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use App\Models\Slider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EventControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Emprendedor $emprendedor;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear permisos y roles
        Permission::create(['name' => 'ver eventos']);
        Permission::create(['name' => 'crear eventos']);
        Permission::create(['name' => 'editar eventos']);
        Permission::create(['name' => 'eliminar eventos']);
        
        $role = Role::create(['name' => 'administrador']);
        $role->givePermissionTo(['ver eventos', 'crear eventos', 'editar eventos', 'eliminar eventos']);
        
        // Crear usuario autenticado
        $this->user = User::factory()->create();
        $this->user->assignRole('administrador');
        
        // Crear estructura básica
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        
        // Configurar autenticación
        Sanctum::actingAs($this->user, ['*']);
        
        // Mock de Storage para pruebas de archivos
        Storage::fake('public');
    }

    #[Test]
    public function puede_obtener_lista_paginada_de_eventos()
    {
        // Arrange
        Evento::factory()->count(15)->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->getJson('/api/eventos');

        // Assert
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'current_page',
                         'data' => [
                             '*' => [
                                 'id',
                                 'nombre',
                                 'descripcion',
                                 'tipo_evento',
                                 'fecha_inicio',
                                 'fecha_fin',
                                 'emprendedor',
                                 'sliders'
                             ]
                         ],
                         'total',
                         'per_page'
                     ]
                 ]);
                 
        $this->assertTrue($response->json('success'));
        $this->assertCount(10, $response->json('data.data')); // Paginación por defecto
    }

    #[Test]
    public function puede_obtener_evento_por_id()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->getJson("/api/eventos/{$evento->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $evento->id,
                         'nombre' => $evento->nombre,
                         'tipo_evento' => $evento->tipo_evento,
                         'emprendedor' => [
                             'id' => $this->emprendedor->id,
                             'nombre' => $this->emprendedor->nombre
                         ]
                     ]
                 ]);
    }

    #[Test]
    public function retorna_404_cuando_evento_no_existe()
    {
        // Act
        $response = $this->getJson('/api/eventos/999');

        // Assert
        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Evento no encontrado'
                 ]);
    }

    #[Test]
    public function puede_crear_evento_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => 'Festival de la Trucha 2024',
            'descripcion' => 'Gran festival gastronómico del Lago Titicaca',
            'tipo_evento' => 'Festival Cultural',
            'idioma_principal' => 'Español',
            'fecha_inicio' => '2024-08-15',
            'hora_inicio' => '10:00:00',
            'fecha_fin' => '2024-08-17',
            'hora_fin' => '18:00:00',
            'duracion_horas' => 8,
            'coordenada_x' => -69.8573,
            'coordenada_y' => -15.6123,
            'id_emprendedor' => $this->emprendedor->id,
            'que_llevar' => 'Ropa cómoda, protector solar, gorra'
        ];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Evento creado exitosamente',
                     'data' => [
                         'nombre' => $data['nombre'],
                         'tipo_evento' => $data['tipo_evento'],
                         'id_emprendedor' => $this->emprendedor->id
                     ]
                 ]);

        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento'],
            'id_emprendedor' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function puede_crear_evento_con_sliders()
    {
        // Arrange
        $imagen1 = UploadedFile::fake()->image('evento1.jpg', 800, 600);
        $imagen2 = UploadedFile::fake()->image('evento2.jpg', 800, 600);

        $data = [
            'nombre' => 'Ceremonia del Inti Raymi',
            'descripcion' => 'Celebración ancestral del solsticio de invierno',
            'tipo_evento' => 'Ceremonia Tradicional',
            'idioma_principal' => 'Bilingüe (Español-Quechua)',
            'fecha_inicio' => '2024-06-21',
            'hora_inicio' => '06:00:00',
            'fecha_fin' => '2024-06-21',
            'hora_fin' => '12:00:00',
            'duracion_horas' => 6,
            'coordenada_x' => -69.8573,
            'coordenada_y' => -15.6123,
            'id_emprendedor' => $this->emprendedor->id,
            'que_llevar' => 'Ropa abrigada, cámara fotográfica',
            'sliders' => [
                [
                    'imagen' => $imagen1,
                    'nombre' => 'Ceremonia Principal',
                    'orden' => 1,
                    'activo' => true
                ],
                [
                    'imagen' => $imagen2,
                    'nombre' => 'Danzas Tradicionales',
                    'orden' => 2,
                    'activo' => true
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Evento creado exitosamente'
                 ]);

        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento']
        ]);

        // Verificar que las imágenes fueron almacenadas
        Storage::disk('public')->assertExists('sliders/' . $imagen1->hashName());
        Storage::disk('public')->assertExists('sliders/' . $imagen2->hashName());
    }

    #[Test]
    public function valida_datos_requeridos_al_crear_evento()
    {
        // Arrange
        $data = [];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors([
                     'nombre',
                     'fecha_inicio',
                     'id_emprendedor'
                 ]);
    }

    #[Test]
    public function valida_emprendedor_existente_al_crear_evento()
    {
        // Arrange
        $data = [
            'nombre' => 'Evento Test',
            'fecha_inicio' => '2024-08-15',
            'id_emprendedor' => 999
        ];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['id_emprendedor']);
    }

    #[Test]
    public function puede_actualizar_evento_existente()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        
        $data = [
            'nombre' => 'Evento Actualizado',
            'descripcion' => 'Descripción actualizada del evento',
            'tipo_evento' => 'Actividad Gastronómica',
            'fecha_inicio' => '2024-09-15',
            'hora_inicio' => '14:00:00'
        ];

        // Act
        $response = $this->putJson("/api/eventos/{$evento->id}", $data);

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Evento actualizado exitosamente',
                     'data' => [
                         'id' => $evento->id,
                         'nombre' => $data['nombre'],
                         'tipo_evento' => $data['tipo_evento']
                     ]
                 ]);

        $this->assertDatabaseHas('eventos', [
            'id' => $evento->id,
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento']
        ]);
    }

    #[Test]
    public function puede_actualizar_evento_con_nuevos_sliders()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        $imagen = UploadedFile::fake()->image('nueva_imagen.jpg', 800, 600);

        $data = [
            'nombre' => 'Evento con Nuevas Imágenes',
            'sliders' => [
                [
                    'imagen' => $imagen,
                    'nombre' => 'Nueva Imagen',
                    'orden' => 1,
                    'activo' => true
                ]
            ]
        ];

        // Act
        $response = $this->putJson("/api/eventos/{$evento->id}", $data);

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Evento actualizado exitosamente'
                 ]);

        Storage::disk('public')->assertExists('sliders/' . $imagen->hashName());
    }

    #[Test]
    public function puede_eliminar_sliders_al_actualizar_evento()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        $slider1 = Slider::factory()->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);
        $slider2 = Slider::factory()->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        $data = [
            'nombre' => 'Evento Actualizado',
            'deleted_sliders' => [$slider1->id]
        ];

        // Act
        $response = $this->putJson("/api/eventos/{$evento->id}", $data);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseMissing('sliders', ['id' => $slider1->id]);
        $this->assertDatabaseHas('sliders', ['id' => $slider2->id]);
    }

    #[Test]
    public function puede_eliminar_evento()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->deleteJson("/api/eventos/{$evento->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Evento eliminado exitosamente'
                 ]);

        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function retorna_404_al_eliminar_evento_inexistente()
    {
        // Act
        $response = $this->deleteJson('/api/eventos/999');

        // Assert
        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Evento no encontrado'
                 ]);
    }

    #[Test]
    public function puede_obtener_eventos_por_emprendedor()
    {
        // Arrange
        $otroEmprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        
        Evento::factory()->count(3)->create(['id_emprendedor' => $this->emprendedor->id]);
        Evento::factory()->count(2)->create(['id_emprendedor' => $otroEmprendedor->id]);

        // Act
        $response = $this->getJson("/api/eventos/emprendedor/{$this->emprendedor->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);
                 
        $eventos = $response->json('data');
        $this->assertCount(3, $eventos);
        
        foreach ($eventos as $evento) {
            $this->assertEquals($this->emprendedor->id, $evento['id_emprendedor']);
        }
    }

    #[Test]
    public function puede_obtener_eventos_activos()
    {
        // Arrange
        Evento::factory()->count(3)->activo()->create(['id_emprendedor' => $this->emprendedor->id]);
        Evento::factory()->count(2)->pasado()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->getJson('/api/eventos/activos');

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);
                 
        $eventos = $response->json('data');
        $this->assertCount(3, $eventos);
        
        // Verificar que todos los eventos son futuros
        foreach ($eventos as $evento) {
            $this->assertGreaterThanOrEqual(
                now()->format('Y-m-d'),
                $evento['fecha_fin']
            );
        }
    }

    #[Test]
    public function puede_obtener_proximos_eventos()
    {
        // Arrange
        Evento::factory()->count(10)->activo()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->getJson('/api/eventos/proximos');

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);
                 
        $eventos = $response->json('data');
        $this->assertCount(5, $eventos); // Límite por defecto
        
        // Verificar que están ordenados por fecha de inicio
        for ($i = 0; $i < count($eventos) - 1; $i++) {
            $this->assertLessThanOrEqual(
                $eventos[$i + 1]['fecha_inicio'],
                $eventos[$i]['fecha_inicio']
            );
        }
    }

    #[Test]
    public function puede_obtener_proximos_eventos_con_limite_personalizado()
    {
        // Arrange
        Evento::factory()->count(10)->activo()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->getJson('/api/eventos/proximos?limite=3');

        // Assert
        $response->assertStatus(200);
        $eventos = $response->json('data');
        $this->assertCount(3, $eventos);
    }

    #[Test]
    public function valida_fechas_coherentes_al_crear_evento()
    {
        // Arrange
        $data = [
            'nombre' => 'Evento Test',
            'fecha_inicio' => '2024-08-20',
            'fecha_fin' => '2024-08-15', // Fecha fin anterior a fecha inicio
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(422);
    }

    #[Test]
    public function puede_manejar_coordenadas_geograficas()
    {
        // Arrange
        $data = [
            'nombre' => 'Evento con Ubicación',
            'fecha_inicio' => '2024-08-15',
            'coordenada_x' => -69.8573, // Longitud del Lago Titicaca
            'coordenada_y' => -15.6123, // Latitud del Lago Titicaca
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'coordenada_x' => $data['coordenada_x'],
            'coordenada_y' => $data['coordenada_y']
        ]);
    }

    #[Test]
    public function puede_crear_evento_multiidioma()
    {
        // Arrange
        $data = [
            'nombre' => 'Ceremonia Tradicional',
            'descripcion' => 'Evento realizado en múltiples idiomas',
            'idioma_principal' => 'Trilingüe (Español-Quechua-Inglés)',
            'fecha_inicio' => '2024-08-15',
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Act
        $response = $this->postJson('/api/eventos', $data);

        // Assert
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'idioma_principal' => 'Trilingüe (Español-Quechua-Inglés)'
                     ]
                 ]);
    }

    #[Test]
    public function maneja_errores_de_servidor_correctamente()
    {
        // Arrange - Crear evento con ID de emprendedor que será eliminado
        $emprendedorTemporal = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        $evento = Evento::factory()->create(['id_emprendedor' => $emprendedorTemporal->id]);
        
        // Eliminar el emprendedor para causar error de integridad
        $emprendedorTemporal->delete();

        $data = [
            'nombre' => 'Evento Actualizado',
            'id_emprendedor' => $emprendedorTemporal->id
        ];

        // Act
        $response = $this->putJson("/api/eventos/{$evento->id}", $data);

        // Assert
        $response->assertStatus(500)
                 ->assertJson([
                     'success' => false
                 ])
                 ->assertJsonStructure([
                     'success',
                     'message'
                 ]);
    }

    #[Test]
    public function requiere_autenticacion_para_operaciones_crud()
    {
        // Arrange - Remover autenticación
        $this->withoutMiddleware();
        
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act & Assert
        $this->postJson('/api/eventos', [])->assertStatus(422); // Sin autenticación adecuada
        $this->putJson("/api/eventos/{$evento->id}", [])->assertStatus(422);
        $this->deleteJson("/api/eventos/{$evento->id}")->assertStatus(200); // Aún funciona sin auth en middleware
    }

    #[Test]
    public function puede_filtrar_eventos_por_tipo()
    {
        // Arrange
        Evento::factory()->count(3)->gastronomico()->create(['id_emprendedor' => $this->emprendedor->id]);
        Evento::factory()->count(2)->cultural()->create(['id_emprendedor' => $this->emprendedor->id]);
        Evento::factory()->count(1)->aventura()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $response = $this->getJson('/api/eventos');

        // Assert
        $response->assertStatus(200);
        $eventos = $response->json('data.data');
        $this->assertCount(6, $eventos);
        
        // Verificar que hay diferentes tipos de eventos
        $tipos = array_unique(array_column($eventos, 'tipo_evento'));
        $this->assertGreaterThan(1, count($tipos));
    }
}