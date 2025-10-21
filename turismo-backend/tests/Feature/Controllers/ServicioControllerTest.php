<?php

namespace Tests\Feature\Controllers;

use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use App\Models\User;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class ServicioControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $normalUser;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear permisos necesarios
        $this->createPermissions();
        
        // Crear roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        
        // Asignar permisos a roles
        $adminRole->givePermissionTo([
            'servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete'
        ]);
        $userRole->givePermissionTo(['servicio_read']);
        
        // Crear usuarios
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        
        $this->normalUser = User::factory()->create();
        $this->normalUser->assignRole('user');
        
        // Crear emprendedor con sus dependencias
        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
        $this->emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $asociacion->id
        ]);
    }

    private function createPermissions(): void
    {
        $permissions = [
            'servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete'
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    #[Test]
    public function puede_listar_todos_los_servicios()
    {
        // Arrange
        Servicio::factory()->count(5)->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $response = $this->getJson('/api/servicios');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'nombre',
                                'descripcion',
                                'precio_referencial',
                                'emprendedor_id',
                                'estado',
                                'capacidad',
                                'latitud',
                                'longitud',
                                'ubicacion_referencia',
                                'created_at',
                                'updated_at'
                            ]
                        ],
                        'current_page',
                        'per_page',
                        'total'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function puede_mostrar_un_servicio_especifico()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $response = $this->getJson("/api/servicios/{$servicio->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $servicio->id,
                        'nombre' => $servicio->nombre,
                        'precio_referencial' => (string) $servicio->precio_referencial
                    ]
                ]);
    }

    #[Test]
    public function retorna_404_cuando_servicio_no_existe()
    {
        // Act
        $response = $this->getJson('/api/servicios/999');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Servicio no encontrado'
                ]);
    }

    #[Test]
    public function admin_puede_crear_nuevo_servicio()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $categoria = Categoria::factory()->create();
        
        $data = [
            'nombre' => 'Tour en Kayak',
            'descripcion' => 'Experiencia única en el lago',
            'precio_referencial' => 45.50,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 6,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'ubicacion_referencia' => 'Muelle Principal',
            'categorias' => [$categoria->id]
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJson([
                    'success' => true,
                    'message' => 'Servicio creado exitosamente'
                ]);

        $this->assertDatabaseHas('servicios', [
            'nombre' => $data['nombre'],
            'precio_referencial' => $data['precio_referencial'],
            'emprendedor_id' => $data['emprendedor_id']
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_crear_servicio()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $data = [
            'nombre' => 'Tour en Kayak',
            'emprendedor_id' => $this->emprendedor->id,
            'precio_referencial' => 45.50
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function usuario_no_autenticado_no_puede_crear_servicio()
    {
        // Arrange
        $data = [
            'nombre' => 'Tour en Kayak',
            'emprendedor_id' => $this->emprendedor->id,
            'precio_referencial' => 45.50
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function falla_validacion_al_crear_servicio_sin_datos_requeridos()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'descripcion' => 'Descripción sin nombre'
            // Faltan campos requeridos
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function admin_puede_actualizar_servicio_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'precio_referencial' => 75.00,
            'estado' => false
        ];

        // Act
        $response = $this->putJson("/api/servicios/{$servicio->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Servicio actualizado exitosamente'
                ]);

        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'nombre' => 'Nombre Actualizado',
            'precio_referencial' => 75.00,
            'estado' => false
        ]);
    }

    #[Test]
    public function retorna_404_al_actualizar_servicio_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $updateData = [
            'nombre' => 'Test'
        ];

        // Act
        $response = $this->putJson('/api/servicios/999', $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Servicio no encontrado'
                ]);
    }

    #[Test]
    public function admin_puede_eliminar_servicio_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $response = $this->deleteJson("/api/servicios/{$servicio->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Servicio eliminado exitosamente'
                ]);

        $this->assertDatabaseMissing('servicios', [
            'id' => $servicio->id
        ]);
    }

    #[Test]
    public function puede_obtener_servicios_por_emprendedor()
    {
        // Arrange
        $otroEmprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->emprendedor->asociacion_id
        ]);

        Servicio::factory()->count(3)->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        Servicio::factory()->count(2)->create([
            'emprendedor_id' => $otroEmprendedor->id
        ]);

        // Act
        $response = $this->getJson("/api/servicios/emprendedor/{$this->emprendedor->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $servicio) {
            $this->assertEquals($this->emprendedor->id, $servicio['emprendedor_id']);
        }
    }

    #[Test]
    public function puede_obtener_servicios_por_categoria()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $otraCategoria = Categoria::factory()->create();

        $servicio1 = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $servicio2 = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);
        $servicio3 = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Asociar servicios a categorías
        $servicio1->categorias()->attach($categoria->id);
        $servicio2->categorias()->attach($categoria->id);
        $servicio3->categorias()->attach($otraCategoria->id);

        // Act
        $response = $this->getJson("/api/servicios/categoria/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function puede_obtener_servicios_por_ubicacion()
    {
        // Arrange
        Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -15.8422,
            'longitud' => -70.0199,
            'estado' => true
        ]);

        Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -16.0000, // Más lejos
            'longitud' => -71.0000,
            'estado' => true
        ]);

        // Act
        $response = $this->getJson('/api/servicios/ubicacion?latitud=-15.8422&longitud=-70.0199&distancia=5');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ]);

        // Al menos debería encontrar el servicio cercano
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    #[Test]
    public function puede_verificar_disponibilidad_de_servicio()
    {
        // Arrange
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $response = $this->getJson("/api/servicios/verificar-disponibilidad?servicio_id={$servicio->id}&fecha=2024-12-25&hora_inicio=09:00:00&hora_fin=11:00:00");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'disponible'
                ]);
    }

    #[Test]
    public function falla_validacion_ubicacion_con_parametros_invalidos()
    {
        // Act
        $response = $this->getJson('/api/servicios/ubicacion?latitud=invalid&longitud=-70.0199');

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJson([
                    'success' => false
                ]);
    }

    #[Test]
    public function falla_validacion_disponibilidad_con_parametros_invalidos()
    {
        // Act
        $response = $this->getJson('/api/servicios/verificar-disponibilidad?servicio_id=999&fecha=invalid-date');

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJson([
                    'success' => false
                ]);
    }

    #[Test]
    public function precio_referencial_se_almacena_correctamente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => 'Servicio con Precio',
            'precio_referencial' => 123.45,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('servicios', [
            'nombre' => 'Servicio con Precio',
            'precio_referencial' => 123.45
        ]);
    }

    #[Test]
    public function estado_se_convierte_a_booleano()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true
        ]);

        $updateData = [
            'estado' => '0' // String que debe convertirse a false
        ];

        // Act
        $response = $this->putJson("/api/servicios/{$servicio->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('servicios', [
            'id' => $servicio->id,
            'estado' => false
        ]);
    }

    #[Test]
    public function coordenadas_se_almacenan_como_decimal()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => 'Servicio con Coordenadas',
            'emprendedor_id' => $this->emprendedor->id,
            'latitud' => -15.8422123,
            'longitud' => -70.0199456,
            'estado' => true,
            'capacidad' => 5
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED);
        
        $servicio = Servicio::latest()->first();
        $this->assertIsFloat($servicio->latitud);
        $this->assertIsFloat($servicio->longitud);
    }

    #[Test]
    public function puede_crear_servicio_con_categorias_multiples()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $categoria1 = Categoria::factory()->create();
        $categoria2 = Categoria::factory()->create();
        
        $data = [
            'nombre' => 'Servicio Multicategoría',
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => true,
            'capacidad' => 5,
            'categorias' => [$categoria1->id, $categoria2->id]
        ];

        // Act
        $response = $this->postJson('/api/servicios', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED);
        
        $servicio = Servicio::latest()->first();
        $this->assertCount(2, $servicio->categorias);
    }
}