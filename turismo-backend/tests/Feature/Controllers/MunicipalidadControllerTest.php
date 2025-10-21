<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Municipalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class MunicipalidadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_get_all_municipalidades()
    {
        Municipalidad::factory()->count(3)->create();

        $response = $this->getJson('/api/municipalidades');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'nombre',
                            'descripcion',
                            'activo',
                        ]
                    ]
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_get_specific_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();

        $response = $this->getJson("/api/municipalidades/{$municipalidad->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'nombre',
                        'descripcion',
                        'activo',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $municipalidad->id,
                        'nombre' => $municipalidad->nombre,
                    ]
                ]);
    }

    /** @test */
    public function get_nonexistent_municipalidad_returns_404()
    {
        $response = $this->getJson('/api/municipalidades/999999');

        $response->assertStatus(404);
    }

    /** @test */
    public function admin_can_create_municipalidad()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $municipalidadData = [
            'nombre' => 'Nueva Municipalidad',
            'descripcion' => 'Descripción de la nueva municipalidad',
            'activo' => true,
        ];

        $response = $this->postJson('/api/municipalidades', $municipalidadData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Municipalidad creada correctamente',
                    'data' => [
                        'nombre' => 'Nueva Municipalidad',
                        'descripcion' => 'Descripción de la nueva municipalidad',
                    ]
                ]);

        $this->assertDatabaseHas('municipalidades', [
            'nombre' => 'Nueva Municipalidad',
        ]);
    }

    /** @test */
    public function create_municipalidad_validates_required_fields()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/municipalidades', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function admin_can_update_municipalidad()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $municipalidad = Municipalidad::factory()->create();

        $updateData = [
            'nombre' => 'Municipalidad Actualizada',
            'descripcion' => 'Nueva descripción',
            'activo' => false,
        ];

        $response = $this->putJson("/api/municipalidades/{$municipalidad->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Municipalidad actualizada correctamente',
                ]);

        $this->assertDatabaseHas('municipalidades', [
            'id' => $municipalidad->id,
            'nombre' => 'Municipalidad Actualizada',
        ]);
    }

    /** @test */
    public function admin_can_delete_municipalidad()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $municipalidad = Municipalidad::factory()->create();

        $response = $this->deleteJson("/api/municipalidades/{$municipalidad->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Municipalidad eliminada correctamente',
                ]);

        $this->assertDatabaseMissing('municipalidades', [
            'id' => $municipalidad->id,
        ]);
    }

    /** @test */
    public function non_admin_cannot_create_municipalidad()
    {
        $municipalidadData = [
            'nombre' => 'Nueva Municipalidad',
            'descripcion' => 'Descripción de la nueva municipalidad',
        ];

        $response = $this->postJson('/api/municipalidades', $municipalidadData);

        $response->assertStatus(403);
    }

    /** @test */
    public function non_admin_cannot_update_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();

        $updateData = [
            'nombre' => 'Municipalidad Actualizada',
        ];

        $response = $this->putJson("/api/municipalidades/{$municipalidad->id}", $updateData);

        $response->assertStatus(403);
    }

    /** @test */
    public function non_admin_cannot_delete_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();

        $response = $this->deleteJson("/api/municipalidades/{$municipalidad->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_can_view_municipalidades()
    {
        auth()->logout();
        
        Municipalidad::factory()->count(2)->create();

        $response = $this->getJson('/api/municipalidades');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_municipalidad()
    {
        auth()->logout();

        $municipalidadData = [
            'nombre' => 'Nueva Municipalidad',
            'descripcion' => 'Descripción',
        ];

        $response = $this->postJson('/api/municipalidades', $municipalidadData);

        $response->assertStatus(401);
    }
}