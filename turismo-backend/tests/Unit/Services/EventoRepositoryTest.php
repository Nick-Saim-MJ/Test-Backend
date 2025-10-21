<?php

namespace Tests\Unit\Services;

use App\Models\Evento;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\Slider;
use App\Repository\EventoRepository;
use App\Repository\SliderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

class EventoRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected EventoRepository $repository;
    protected Emprendedor $emprendedor;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock SliderRepository para evitar dependencias
        $sliderRepositoryMock = Mockery::mock(SliderRepository::class);
        $sliderRepositoryMock->shouldReceive('createMultiple')->andReturn(true);
        $sliderRepositoryMock->shouldReceive('updateEntitySliders')->andReturn(true);
        $sliderRepositoryMock->shouldReceive('delete')->andReturn(true);
        
        $this->repository = new EventoRepository(new Evento(), $sliderRepositoryMock);
        
        // Crear estructura básica
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function puede_obtener_todos_los_eventos()
    {
        // Arrange
        $eventos = Evento::factory()->count(5)->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(5, $result);
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_eventos_paginados()
    {
        // Arrange
        Evento::factory()->count(25)->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $result = $this->repository->getPaginated(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(25, $result->total());
        $this->assertCount(10, $result->items());
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->items()[0]->relationLoaded('emprendedor'));
        $this->assertTrue($result->items()[0]->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_evento_por_id()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $result = $this->repository->getById($evento->id);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($evento->id, $result->id);
        $this->assertEquals($evento->nombre, $result->nombre);
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->relationLoaded('emprendedor'));
        $this->assertTrue($result->relationLoaded('sliders'));
    }

    #[Test]
    public function retorna_null_cuando_evento_no_existe()
    {
        // Act
        $result = $this->repository->getById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_evento_sin_sliders()
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
            'que_llevar' => 'Ropa cómoda, protector solar'
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['tipo_evento'], $result->tipo_evento);
        $this->assertEquals($data['id_emprendedor'], $result->id_emprendedor);
        
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
        $data = [
            'nombre' => 'Ceremonia del Inti Raymi',
            'tipo_evento' => 'Ceremonia Tradicional',
            'fecha_inicio' => '2024-06-21',
            'id_emprendedor' => $this->emprendedor->id,
            'sliders' => [
                [
                    'url' => 'imagen1.jpg',
                    'nombre' => 'Ceremonia Principal',
                    'orden' => 1,
                    'activo' => true,
                    'es_principal' => true
                ],
                [
                    'url' => 'imagen2.jpg',
                    'nombre' => 'Danzas Tradicionales',
                    'orden' => 2,
                    'activo' => true,
                    'es_principal' => true
                ]
            ]
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertDatabaseHas('eventos', [
            'nombre' => $data['nombre'],
            'id_emprendedor' => $this->emprendedor->id
        ]);
    }

    #[Test]
    public function puede_actualizar_evento_existente()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        
        $data = [
            'nombre' => 'Evento Actualizado',
            'descripcion' => 'Descripción actualizada',
            'tipo_evento' => 'Actividad Gastronómica',
            'duracion_horas' => 10
        ];

        // Act
        $result = $this->repository->update($evento->id, $data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['tipo_evento'], $result->tipo_evento);
        $this->assertEquals($data['duracion_horas'], $result->duracion_horas);
        
        $this->assertDatabaseHas('eventos', [
            'id' => $evento->id,
            'nombre' => $data['nombre'],
            'tipo_evento' => $data['tipo_evento']
        ]);
    }

    #[Test]
    public function puede_actualizar_evento_con_sliders()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        
        $data = [
            'nombre' => 'Evento con Nuevos Sliders',
            'sliders' => [
                [
                    'url' => 'nueva_imagen.jpg',
                    'nombre' => 'Nueva Imagen',
                    'orden' => 1,
                    'activo' => true,
                    'es_principal' => true
                ]
            ]
        ];

        // Act
        $result = $this->repository->update($evento->id, $data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    #[Test]
    public function puede_actualizar_evento_eliminando_sliders()
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
        $result = $this->repository->update($evento->id, $data);

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    #[Test]
    public function lanza_excepcion_al_actualizar_evento_inexistente()
    {
        // Arrange
        $data = ['nombre' => 'Test'];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Evento no encontrado');
        
        $this->repository->update(999, $data);
    }

    #[Test]
    public function puede_eliminar_evento_existente()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $result = $this->repository->delete($evento->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function puede_eliminar_evento_con_sliders()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        $sliders = Slider::factory()->count(2)->create([
            'entidad_id' => $evento->id,
            'tipo_entidad' => 'evento'
        ]);

        // Act
        $result = $this->repository->delete($evento->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_evento_inexistente()
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_obtener_eventos_por_emprendedor()
    {
        // Arrange
        $otroEmprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        
        $eventosEmprendedor1 = Evento::factory()->count(3)->create(['id_emprendedor' => $this->emprendedor->id]);
        $eventosEmprendedor2 = Evento::factory()->count(2)->create(['id_emprendedor' => $otroEmprendedor->id]);

        // Act
        $result = $this->repository->getEventosByEmprendedor($this->emprendedor->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        
        foreach ($result as $evento) {
            $this->assertEquals($this->emprendedor->id, $evento->id_emprendedor);
        }
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_eventos_activos()
    {
        // Arrange
        $fechaActual = now()->format('Y-m-d');
        $fechaFutura = now()->addDays(30)->format('Y-m-d');
        $fechaPasada = now()->subDays(30)->format('Y-m-d');

        // Eventos activos (futuros)
        $eventosActivos = Evento::factory()->count(3)->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_fin' => $fechaFutura
        ]);

        // Eventos pasados
        $eventosPasados = Evento::factory()->count(2)->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_fin' => $fechaPasada
        ]);

        // Act
        $result = $this->repository->getEventosActivos();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        
        foreach ($result as $evento) {
            $this->assertGreaterThanOrEqual($fechaActual, $evento->fecha_fin);
        }
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('sliders'));
        
        // Verificar que están ordenados por fecha_inicio
        $fechasInicio = $result->pluck('fecha_inicio')->toArray();
        $fechasOrdenadas = $fechasInicio;
        sort($fechasOrdenadas);
        $this->assertEquals($fechasOrdenadas, $fechasInicio);
    }

    #[Test]
    public function puede_obtener_proximos_eventos()
    {
        // Arrange
        $fechaActual = now()->format('Y-m-d');
        $fechaFutura1 = now()->addDays(10)->format('Y-m-d');
        $fechaFutura2 = now()->addDays(20)->format('Y-m-d');
        $fechaFutura3 = now()->addDays(30)->format('Y-m-d');

        // Crear eventos futuros
        $evento1 = Evento::factory()->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_inicio' => $fechaFutura2
        ]);
        
        $evento2 = Evento::factory()->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_inicio' => $fechaFutura1
        ]);
        
        $evento3 = Evento::factory()->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_inicio' => $fechaFutura3
        ]);

        // Act
        $result = $this->repository->getProximosEventos(2);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        
        // Verificar que están ordenados por fecha_inicio
        $this->assertEquals($evento2->id, $result[0]->id); // 10 días
        $this->assertEquals($evento1->id, $result[1]->id); // 20 días
        
        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('emprendedor'));
        $this->assertTrue($result->first()->relationLoaded('sliders'));
    }

    #[Test]
    public function puede_obtener_proximos_eventos_con_limite_por_defecto()
    {
        // Arrange
        Evento::factory()->count(10)->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_inicio' => now()->addDays(10)->format('Y-m-d')
        ]);

        // Act
        $result = $this->repository->getProximosEventos();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(5, $result); // Límite por defecto
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_creacion()
    {
        // Arrange
        $data = [
            'nombre' => 'Evento Transaccional',
            'fecha_inicio' => '2024-08-15',
            'id_emprendedor' => $this->emprendedor->id
        ];

        // Act
        DB::beginTransaction();
        $result = $this->repository->create($data);
        DB::commit();

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertDatabaseHas('eventos', [
            'nombre' => 'Evento Transaccional'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_actualizacion()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);
        $data = ['nombre' => 'Actualizado con Transacción'];

        // Act
        DB::beginTransaction();
        $result = $this->repository->update($evento->id, $data);
        DB::commit();

        // Assert
        $this->assertInstanceOf(Evento::class, $result);
        $this->assertDatabaseHas('eventos', [
            'id' => $evento->id,
            'nombre' => 'Actualizado con Transacción'
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_eliminacion()
    {
        // Arrange
        $evento = Evento::factory()->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        DB::beginTransaction();
        $result = $this->repository->delete($evento->id);
        DB::commit();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    #[Test]
    public function puede_usar_paginacion_con_diferentes_tamaños()
    {
        // Arrange
        Evento::factory()->count(25)->create(['id_emprendedor' => $this->emprendedor->id]);

        // Act
        $resultados5 = $this->repository->getPaginated(5);
        $resultados10 = $this->repository->getPaginated(10);
        $resultados15 = $this->repository->getPaginated(15);

        // Assert
        $this->assertEquals(5, $resultados5->perPage());
        $this->assertEquals(10, $resultados10->perPage());
        $this->assertEquals(15, $resultados15->perPage());
        
        $this->assertEquals(25, $resultados5->total());
        $this->assertEquals(25, $resultados10->total());
        $this->assertEquals(25, $resultados15->total());
    }

    #[Test]
    public function filtra_eventos_por_emprendedor_correctamente()
    {
        // Arrange
        $emprendedor1 = $this->emprendedor;
        $emprendedor2 = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        $emprendedor3 = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);

        Evento::factory()->count(3)->create(['id_emprendedor' => $emprendedor1->id]);
        Evento::factory()->count(2)->create(['id_emprendedor' => $emprendedor2->id]);
        Evento::factory()->count(4)->create(['id_emprendedor' => $emprendedor3->id]);

        // Act
        $eventos1 = $this->repository->getEventosByEmprendedor($emprendedor1->id);
        $eventos2 = $this->repository->getEventosByEmprendedor($emprendedor2->id);
        $eventos3 = $this->repository->getEventosByEmprendedor($emprendedor3->id);

        // Assert
        $this->assertCount(3, $eventos1);
        $this->assertCount(2, $eventos2);
        $this->assertCount(4, $eventos3);

        foreach ($eventos1 as $evento) {
            $this->assertEquals($emprendedor1->id, $evento->id_emprendedor);
        }
    }

    #[Test]
    public function maneja_eventos_con_fechas_identicas()
    {
        // Arrange
        $fechaComun = now()->addDays(15)->format('Y-m-d');
        
        $eventos = Evento::factory()->count(3)->create([
            'id_emprendedor' => $this->emprendedor->id,
            'fecha_inicio' => $fechaComun,
            'fecha_fin' => $fechaComun
        ]);

        // Act
        $eventosActivos = $this->repository->getEventosActivos();
        $proximosEventos = $this->repository->getProximosEventos();

        // Assert
        $this->assertCount(3, $eventosActivos);
        $this->assertCount(3, $proximosEventos);
    }

    #[Test]
    public function puede_manejar_coleccion_vacia()
    {
        // Act
        $todos = $this->repository->getAll();
        $paginados = $this->repository->getPaginated();
        $activos = $this->repository->getEventosActivos();
        $proximos = $this->repository->getProximosEventos();

        // Assert
        $this->assertInstanceOf(Collection::class, $todos);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginados);
        $this->assertInstanceOf(Collection::class, $activos);
        $this->assertInstanceOf(Collection::class, $proximos);
        
        $this->assertCount(0, $todos);
        $this->assertEquals(0, $paginados->total());
        $this->assertCount(0, $activos);
        $this->assertCount(0, $proximos);
    }
}