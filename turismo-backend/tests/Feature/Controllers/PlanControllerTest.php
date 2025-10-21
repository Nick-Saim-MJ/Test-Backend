<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plan;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Emprendedor $emprendedor;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear roles básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);

        $this->user = User::factory()->create();
        
        $asociacion = Asociacion::factory()->create();
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        
        $this->plan = Plan::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'creado_por_usuario_id' => $this->user->id,
            'estado' => 'activo',
            'es_publico' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_get_all_plans()
    {
        Plan::factory()->count(3)->create();

        $response = $this->getJson('/api/planes');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'nombre',
                                'descripcion',
                                'estado',
                                'es_publico',
                                'duracion_dias',
                                'precio_total',
                            ]
                        ],
                        'current_page',
                        'per_page',
                        'total',
                    ]
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_get_plans_with_filters()
    {
        Plan::factory()->create(['estado' => 'inactivo']);
        Plan::factory()->create(['estado' => 'activo', 'es_publico' => false]);

        $response = $this->getJson('/api/planes?estado=activo&es_publico=1');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_can_create_plan()
    {
        $planData = [
            'nombre' => 'Plan de Prueba',
            'descripcion' => 'Descripción del plan de prueba',
            'emprendedor_id' => $this->emprendedor->id,
            'duracion_dias' => 3,
            'precio_total' => 150.00,
            'cupos_disponibles' => 10,
            'dificultad' => 'facil',
            'es_publico' => true,
            'estado' => 'borrador',
            'incluye' => 'Transporte, alimentación',
            'no_incluye' => 'Propinas',
            'recomendaciones' => 'Llevar ropa cómoda',
            'que_llevar' => 'Bloqueador solar',
        ];

        $response = $this->postJson('/api/planes', $planData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'nombre',
                        'descripcion',
                        'creado_por_usuario_id',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Plan creado correctamente',
                    'data' => [
                        'nombre' => 'Plan de Prueba',
                        'creado_por_usuario_id' => $this->user->id,
                    ]
                ]);

        $this->assertDatabaseHas('planes', [
            'nombre' => 'Plan de Prueba',
            'creado_por_usuario_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function create_plan_validates_required_fields()
    {
        $response = $this->postJson('/api/planes', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'nombre',
                    'descripcion',
                    'emprendedor_id',
                    'duracion_dias',
                    'precio_total',
                ]);
    }

    /** @test */
    public function user_can_get_specific_plan()
    {
        $response = $this->getJson("/api/planes/{$this->plan->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'nombre',
                        'descripcion',
                        'emprendedor',
                        'dias',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $this->plan->id,
                        'nombre' => $this->plan->nombre,
                    ]
                ]);
    }

    /** @test */
    public function get_nonexistent_plan_returns_404()
    {
        $response = $this->getJson('/api/planes/999999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Plan no encontrado',
                ]);
    }

    /** @test */
    public function plan_creator_can_update_plan()
    {
        $updateData = [
            'nombre' => 'Plan Actualizado',
            'descripcion' => 'Nueva descripción',
            'duracion_dias' => 5,
            'precio_total' => 200.00,
            'emprendedor_id' => $this->emprendedor->id,
        ];

        $response = $this->putJson("/api/planes/{$this->plan->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Plan actualizado correctamente',
                    'data' => [
                        'nombre' => 'Plan Actualizado',
                        'descripcion' => 'Nueva descripción',
                    ]
                ]);

        $this->assertDatabaseHas('planes', [
            'id' => $this->plan->id,
            'nombre' => 'Plan Actualizado',
        ]);
    }

    /** @test */
    public function update_nonexistent_plan_returns_404()
    {
        $updateData = [
            'nombre' => 'Plan Actualizado',
            'descripcion' => 'Nueva descripción',
            'duracion_dias' => 5,
            'precio_total' => 200.00,
            'emprendedor_id' => $this->emprendedor->id,
        ];

        $response = $this->putJson('/api/planes/999999', $updateData);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Plan no encontrado',
                ]);
    }

    /** @test */
    public function plan_creator_can_delete_plan()
    {
        $response = $this->deleteJson("/api/planes/{$this->plan->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Plan eliminado correctamente',
                ]);

        $this->assertDatabaseMissing('planes', [
            'id' => $this->plan->id,
        ]);
    }

    /** @test */
    public function delete_nonexistent_plan_returns_404()
    {
        $response = $this->deleteJson('/api/planes/999999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Plan no encontrado',
                ]);
    }

    /** @test */
    public function user_can_search_plans()
    {
        Plan::factory()->create(['nombre' => 'Plan de Aventura']);
        Plan::factory()->create(['nombre' => 'Plan Relajante']);

        $response = $this->getJson('/api/planes/buscar?q=aventura');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function search_requires_search_term()
    {
        $response = $this->getJson('/api/planes/buscar');

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Término de búsqueda requerido',
                ]);
    }

    /** @test */
    public function user_can_get_plan_statistics()
    {
        $response = $this->getJson("/api/planes/{$this->plan->id}/estadisticas");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function get_statistics_for_nonexistent_plan_returns_404()
    {
        $response = $this->getJson('/api/planes/999999/estadisticas');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Plan no encontrado',
                ]);
    }

    /** @test */
    public function plan_creator_can_change_plan_status()
    {
        $response = $this->postJson("/api/planes/{$this->plan->id}/estado", [
            'estado' => 'inactivo'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Estado del plan actualizado correctamente',
                ]);

        $this->assertDatabaseHas('planes', [
            'id' => $this->plan->id,
            'estado' => 'inactivo',
        ]);
    }

    /** @test */
    public function change_status_validates_valid_status()
    {
        $response = $this->postJson("/api/planes/{$this->plan->id}/estado", [
            'estado' => 'invalid_status'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Estado no válido',
                ]);
    }

    /** @test */
    public function unauthorized_user_cannot_change_plan_status()
    {
        $otherUser = User::factory()->create();
        $otherPlan = Plan::factory()->create([
            'creado_por_usuario_id' => $otherUser->id,
        ]);

        $response = $this->postJson("/api/planes/{$otherPlan->id}/estado", [
            'estado' => 'inactivo'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar este plan',
                ]);
    }

    /** @test */
    public function admin_can_change_any_plan_status()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $otherUser = User::factory()->create();
        $otherPlan = Plan::factory()->create([
            'creado_por_usuario_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/planes/{$otherPlan->id}/estado", [
            'estado' => 'inactivo'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Estado del plan actualizado correctamente',
                ]);
    }

    /** @test */
    public function user_can_get_public_plans()
    {
        Plan::factory()->count(3)->create(['es_publico' => true, 'estado' => 'activo']);
        Plan::factory()->create(['es_publico' => false, 'estado' => 'activo']);

        $response = $this->getJson('/api/planes/publicos');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'nombre',
                                'descripcion',
                                'es_publico',
                            ]
                        ]
                    ],
                    'meta' => [
                        'filtros_aplicados',
                        'per_page',
                        'current_page',
                        'total',
                    ]
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function public_plans_can_be_filtered()
    {
        Plan::factory()->create([
            'es_publico' => true,
            'estado' => 'activo',
            'dificultad' => 'facil',
            'precio_total' => 100
        ]);
        
        Plan::factory()->create([
            'es_publico' => true,
            'estado' => 'activo',
            'dificultad' => 'moderado',
            'precio_total' => 300
        ]);

        $response = $this->getJson('/api/planes/publicos?dificultad=facil&precio_max=200');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'meta' => [
                        'filtros_aplicados' => [
                            'dificultad' => 'facil',
                            'precio_max' => '200'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_plan()
    {
        auth()->logout();

        $planData = [
            'nombre' => 'Plan de Prueba',
            'descripcion' => 'Descripción del plan',
            'emprendedor_id' => $this->emprendedor->id,
            'duracion_dias' => 3,
            'precio_total' => 150.00,
        ];

        $response = $this->postJson('/api/planes', $planData);

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_can_view_public_plans()
    {
        auth()->logout();

        $response = $this->getJson('/api/planes/publicos');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }
}