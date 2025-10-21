<?php

namespace Tests\Feature\Controllers;

use App\Models\Categoria;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class CategoriaControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $normalUser;

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
            'categoria_create', 'categoria_read', 'categoria_update', 'categoria_delete'
        ]);
        $userRole->givePermissionTo(['categoria_read']);
        
        // Crear usuarios
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        
        $this->normalUser = User::factory()->create();
        $this->normalUser->assignRole('user');
    }

    private function createPermissions(): void
    {
        $permissions = [
            'categoria_create', 'categoria_read', 'categoria_update', 'categoria_delete'
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    #[Test]
    public function puede_listar_todas_las_categorias()
    {
        // Arrange
        Categoria::factory()->count(5)->create();

        // Act
        $response = $this->getJson('/api/categorias');

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'nombre',
                            'descripcion',
                            'icono_url',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]);

        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function puede_mostrar_una_categoria_especifica()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $response = $this->getJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $categoria->id,
                        'nombre' => $categoria->nombre,
                        'descripcion' => $categoria->descripcion
                    ]
                ]);
    }

    #[Test]
    public function retorna_404_cuando_categoria_no_existe()
    {
        // Act
        $response = $this->getJson('/api/categorias/999');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ]);
    }

    #[Test]
    public function admin_puede_crear_nueva_categoria()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text,
            'icono_url' => $this->faker->imageUrl()
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJson([
                    'success' => true,
                    'message' => 'Categoría creada exitosamente'
                ]);

        $this->assertDatabaseHas('categorias', [
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion']
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_crear_categoria()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function usuario_no_autenticado_no_puede_crear_categoria()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function falla_validacion_al_crear_categoria_sin_datos_requeridos()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'descripcion' => $this->faker->text
            // Falta nombre requerido
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJson([
                    'success' => false,
                    'errors' => [
                        'nombre' => ['The nombre field is required.']
                    ]
                ]);
    }

    #[Test]
    public function puede_crear_categoria_solo_con_nombre()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => 'Categoría Mínima'
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJson([
                    'success' => true,
                    'message' => 'Categoría creada exitosamente'
                ]);

        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Categoría Mínima',
            'descripcion' => null,
            'icono_url' => null
        ]);
    }

    #[Test]
    public function admin_puede_actualizar_categoria_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $categoria = Categoria::factory()->create();

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción'
        ];

        // Act
        $response = $this->putJson("/api/categorias/{$categoria->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Categoría actualizada exitosamente'
                ]);

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Nueva descripción'
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_actualizar_categoria()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $categoria = Categoria::factory()->create();

        $updateData = [
            'nombre' => 'Nombre Actualizado'
        ];

        // Act
        $response = $this->putJson("/api/categorias/{$categoria->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function retorna_404_al_actualizar_categoria_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $updateData = [
            'nombre' => 'Test'
        ];

        // Act
        $response = $this->putJson('/api/categorias/999', $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ]);
    }

    #[Test]
    public function admin_puede_eliminar_categoria_existente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $categoria = Categoria::factory()->create();

        // Act
        $response = $this->deleteJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true,
                    'message' => 'Categoría eliminada exitosamente'
                ]);

        $this->assertDatabaseMissing('categorias', [
            'id' => $categoria->id
        ]);
    }

    #[Test]
    public function usuario_normal_no_puede_eliminar_categoria()
    {
        // Arrange
        Sanctum::actingAs($this->normalUser);
        
        $categoria = Categoria::factory()->create();

        // Act
        $response = $this->deleteJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function retorna_404_al_eliminar_categoria_inexistente()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        // Act
        $response = $this->deleteJson('/api/categorias/999');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                ->assertJson([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ]);
    }

    #[Test]
    public function puede_obtener_categoria_con_servicios()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $response = $this->getJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'nombre',
                        'descripcion',
                        'icono_url',
                        'servicios'
                    ]
                ]);
    }

    #[Test]
    public function respuesta_json_tiene_estructura_correcta_en_exito()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $response = $this->getJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'nombre',
                'descripcion',
                'icono_url',
                'created_at',
                'updated_at',
                'servicios'
            ]
        ]);
    }

    #[Test]
    public function respuesta_json_tiene_estructura_correcta_en_error()
    {
        // Act
        $response = $this->getJson('/api/categorias/999');

        // Assert
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
        
        $this->assertFalse($response->json('success'));
    }

    #[Test]
    public function icono_url_puede_ser_nulo()
    {
        // Arrange
        $categoria = Categoria::factory()->sinIcono()->create();

        // Act
        $response = $this->getJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        
        $this->assertNull($data['icono_url']);
    }

    #[Test]
    public function descripcion_puede_ser_nula()
    {
        // Arrange
        $categoria = Categoria::factory()->sinDescripcion()->create();

        // Act
        $response = $this->getJson("/api/categorias/{$categoria->id}");

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        
        $this->assertNull($data['descripcion']);
    }

    #[Test]
    public function puede_actualizar_campos_opcionales_a_null()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $categoria = Categoria::factory()->conDescripcion()->conIcono()->create();

        $updateData = [
            'descripcion' => null,
            'icono_url' => null
        ];

        // Act
        $response = $this->putJson("/api/categorias/{$categoria->id}", $updateData);

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        
        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'descripcion' => null,
            'icono_url' => null
        ]);
    }

    #[Test]
    public function valida_longitud_maxima_del_nombre()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => str_repeat('a', 256) // Excede el límite de 255 caracteres
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors(['nombre']);
    }

    #[Test]
    public function valida_longitud_maxima_del_icono_url()
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'nombre' => 'Test',
            'icono_url' => str_repeat('a', 256) // Excede el límite de 255 caracteres
        ];

        // Act
        $response = $this->postJson('/api/categorias', $data);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors(['icono_url']);
    }
}