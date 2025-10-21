<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear roles y permisos básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);
        
        Permission::create(['name' => 'view dashboard']);
        Permission::create(['name' => 'manage users']);
        
        // Crear usuario administrador
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        
        Sanctum::actingAs($this->adminUser);
    }

    /** @test */
    public function admin_can_get_dashboard_summary()
    {
        // Crear usuarios adicionales para probar estadísticas
        $activeUser = User::factory()->create(['active' => true]);
        $inactiveUser = User::factory()->create(['active' => false]);
        
        $emprendedorUser = User::factory()->create();
        $emprendedorUser->assignRole('emprendedor');
        
        $turistaUser = User::factory()->create();
        $turistaUser->assignRole('turista');

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_users',
                        'active_users',
                        'inactive_users',
                        'users_by_role' => [
                            '*' => [
                                'role',
                                'count'
                            ]
                        ],
                        'total_roles',
                        'total_permissions',
                        'recent_users' => [
                            '*' => [
                                'id',
                                'name',
                                'email',
                                'active',
                                'roles'
                            ]
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                ]);

        // Verificar que los conteos sean correctos
        $data = $response->json('data');
        
        $this->assertEquals(5, $data['total_users']); // admin + active + inactive + emprendedor + turista
        $this->assertEquals(4, $data['active_users']); // Por defecto active es true excepto inactive
        $this->assertEquals(1, $data['inactive_users']);
        $this->assertEquals(3, $data['total_roles']);
        $this->assertEquals(2, $data['total_permissions']);
        $this->assertCount(5, $data['recent_users']);
    }

    /** @test */
    public function dashboard_summary_shows_users_by_role()
    {
        // Crear usuarios con diferentes roles
        $emprendedor1 = User::factory()->create();
        $emprendedor1->assignRole('emprendedor');
        
        $emprendedor2 = User::factory()->create();
        $emprendedor2->assignRole('emprendedor');
        
        $turista = User::factory()->create();
        $turista->assignRole('turista');

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
        
        $usersByRole = $response->json('data.users_by_role');
        
        // Verificar que tengamos estadísticas por rol
        $this->assertIsArray($usersByRole);
        $this->assertCount(3, $usersByRole); // admin, emprendedor, turista
        
        // Buscar las estadísticas de cada rol
        $roleStats = collect($usersByRole)->keyBy('role');
        
        $this->assertEquals(1, $roleStats['admin']['count']);
        $this->assertEquals(2, $roleStats['emprendedor']['count']);
        $this->assertEquals(1, $roleStats['turista']['count']);
    }

    /** @test */
    public function dashboard_summary_shows_recent_users_with_roles()
    {
        // Crear un usuario con rol específico
        $recentUser = User::factory()->create(['name' => 'Usuario Reciente']);
        $recentUser->assignRole('turista');

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
        
        $recentUsers = $response->json('data.recent_users');
        
        $this->assertIsArray($recentUsers);
        $this->assertCount(2, $recentUsers); // admin + nuevo usuario
        
        // Verificar que el usuario más reciente aparezca primero
        $this->assertEquals('Usuario Reciente', $recentUsers[0]['name']);
        $this->assertArrayHasKey('roles', $recentUsers[0]);
    }

    /** @test */
    public function dashboard_summary_counts_active_and_inactive_users()
    {
        // Crear usuarios activos e inactivos
        User::factory()->count(3)->create(['active' => true]);
        User::factory()->count(2)->create(['active' => false]);

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Total: 1 admin + 3 activos + 2 inactivos = 6
        $this->assertEquals(6, $data['total_users']);
        // Activos: 1 admin + 3 activos = 4
        $this->assertEquals(4, $data['active_users']);
        // Inactivos: 2
        $this->assertEquals(2, $data['inactive_users']);
    }

    /** @test */
    public function dashboard_summary_returns_correct_structure_with_no_additional_users()
    {
        // Solo el usuario admin creado en setUp
        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total_users' => 1,
                        'active_users' => 1,
                        'inactive_users' => 0,
                        'total_roles' => 3,
                        'total_permissions' => 2,
                    ]
                ]);

        $usersByRole = $response->json('data.users_by_role');
        $this->assertCount(3, $usersByRole);
        
        $recentUsers = $response->json('data.recent_users');
        $this->assertCount(1, $recentUsers);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_dashboard()
    {
        auth()->logout();

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(401);
    }

    /** @test */
    public function dashboard_shows_users_without_roles()
    {
        // Crear un usuario sin rol asignado
        User::factory()->create(['name' => 'Usuario Sin Rol']);

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
        
        // Debería funcionar correctamente incluso con usuarios sin roles
        $data = $response->json('data');
        $this->assertEquals(2, $data['total_users']); // admin + usuario sin rol
        
        $usersByRole = $response->json('data.users_by_role');
        $roleStats = collect($usersByRole)->keyBy('role');
        
        // El admin debe aparecer en las estadísticas
        $this->assertEquals(1, $roleStats['admin']['count']);
        // Los otros roles deben tener 0 usuarios
        $this->assertEquals(0, $roleStats['emprendedor']['count']);
        $this->assertEquals(0, $roleStats['turista']['count']);
    }

    /** @test */
    public function dashboard_handles_large_number_of_users()
    {
        // Crear muchos usuarios para probar el límite de usuarios recientes
        User::factory()->count(10)->create();

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Total debe ser correcto
        $this->assertEquals(11, $data['total_users']); // 1 admin + 10 nuevos
        
        // Usuarios recientes debe limitarse a 5
        $recentUsers = $response->json('data.recent_users');
        $this->assertCount(5, $recentUsers);
    }

    /** @test */
    public function dashboard_summary_response_time_is_reasonable()
    {
        // Crear una cantidad moderada de datos para test de rendimiento
        User::factory()->count(20)->create();
        
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/dashboard/summary');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        $response->assertStatus(200);
        
        // El endpoint debería responder en menos de 1 segundo
        $this->assertLessThan(1.0, $executionTime, 'Dashboard summary took too long to respond');
    }
}