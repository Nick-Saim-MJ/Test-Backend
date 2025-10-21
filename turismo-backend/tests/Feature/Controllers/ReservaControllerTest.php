<?php

namespace Tests\Feature\Controllers;

use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReservaControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $admin;
    protected Emprendedor $emprendedor;
    protected Servicio $servicio;
    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear permisos y roles
        Permission::create(['name' => 'ver reservas']);
        Permission::create(['name' => 'crear reservas']);
        Permission::create(['name' => 'editar reservas']);
        Permission::create(['name' => 'eliminar reservas']);
        
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        
        $adminRole->givePermissionTo(['ver reservas', 'crear reservas', 'editar reservas', 'eliminar reservas']);
        $userRole->givePermissionTo(['ver reservas', 'crear reservas']);
        
        // Crear usuarios
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        
        // Crear estructura básica
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->asociacion->id]);
        $this->servicio = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);
    }

    #[Test]
    public function admin_puede_obtener_todas_las_reservas()
    {
        // Arrange
        Sanctum::actingAs($this->admin, ['*']);
        
        Reserva::factory()->count(5)->create(['usuario_id' => $this->user->id]);
        Reserva::factory()->count(3)->create(['usuario_id' => $this->admin->id]);

        // Act
        $response = $this->getJson('/api/reservas');

        // Assert
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'current_page',
                         'data' => [
                             '*' => [
                                 'id',
                                 'codigo_reserva',
                                 'estado',
                                 'usuario',
                                 'servicios'
                             ]
                         ],
                         'total'
                     ]
                 ]);
                 
        $this->assertTrue($response->json('success'));
        $this->assertEquals(8, $response->json('data.total'));
    }

    #[Test]
    public function usuario_solo_puede_ver_sus_propias_reservas()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $reservasUsuario = Reserva::factory()->count(3)->create(['usuario_id' => $this->user->id]);
        $reservasOtroUsuario = Reserva::factory()->count(2)->create(['usuario_id' => $this->admin->id]);

        // Act
        $response = $this->getJson('/api/reservas');

        // Assert
        $response->assertStatus(200);
        $reservas = $response->json('data');
        
        $this->assertCount(3, $reservas);
        foreach ($reservas as $reserva) {
            $this->assertEquals($this->user->id, $reserva['usuario_id']);
        }
    }

    #[Test]
    public function puede_obtener_reserva_por_id()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $response = $this->getJson("/api/reservas/{$reserva->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $reserva->id,
                         'codigo_reserva' => $reserva->codigo_reserva,
                         'estado' => $reserva->estado,
                         'usuario_id' => $this->user->id
                     ]
                 ]);
    }

    #[Test]
    public function usuario_no_puede_ver_reserva_de_otro_usuario()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reservaOtroUsuario = Reserva::factory()->create(['usuario_id' => $this->admin->id]);

        // Act
        $response = $this->getJson("/api/reservas/{$reservaOtroUsuario->id}");

        // Assert
        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'No tienes permiso para ver esta reserva'
                 ]);
    }

    #[Test]
    public function admin_puede_ver_cualquier_reserva()
    {
        // Arrange
        Sanctum::actingAs($this->admin, ['*']);
        $reservaUsuario = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $response = $this->getJson("/api/reservas/{$reservaUsuario->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $reservaUsuario->id,
                         'usuario_id' => $this->user->id
                     ]
                 ]);
    }

    #[Test]
    public function retorna_404_cuando_reserva_no_existe()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);

        // Act
        $response = $this->getJson('/api/reservas/999');

        // Assert
        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Reserva no encontrada'
                 ]);
    }

    #[Test]
    public function puede_crear_reserva_con_servicios()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $data = [
            'notas' => 'Reserva para viaje familiar',
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'fecha_fin' => '2024-08-15',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480,
                    'cantidad' => 2,
                    'precio' => 150.00,
                    'notas_cliente' => 'Preferencia por la mañana'
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/reservas', $data);

        // Assert
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Reserva creada exitosamente'
                 ]);

        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->user->id,
            'notas' => $data['notas']
        ]);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'cantidad' => 2,
            'precio' => 150.00
        ]);
    }

    #[Test]
    public function valida_datos_requeridos_al_crear_reserva()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $data = [];

        // Act
        $response = $this->postJson('/api/reservas', $data);

        // Assert
        $response->assertStatus(422);
    }

    #[Test]
    public function puede_actualizar_reserva_propia()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        $data = [
            'notas' => 'Notas actualizadas de la reserva',
            'estado' => 'confirmada'
        ];

        // Act
        $response = $this->putJson("/api/reservas/{$reserva->id}", $data);

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Reserva actualizada exitosamente'
                 ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'notas' => $data['notas']
        ]);
    }

    #[Test]
    public function no_puede_actualizar_reserva_de_otro_usuario()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reservaOtroUsuario = Reserva::factory()->create(['usuario_id' => $this->admin->id]);
        
        $data = ['notas' => 'Intentando actualizar'];

        // Act
        $response = $this->putJson("/api/reservas/{$reservaOtroUsuario->id}", $data);

        // Assert
        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'No tienes permiso para modificar esta reserva'
                 ]);
    }

    #[Test]
    public function admin_puede_actualizar_cualquier_reserva()
    {
        // Arrange
        Sanctum::actingAs($this->admin, ['*']);
        $reservaUsuario = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        
        $data = ['notas' => 'Actualizada por admin'];

        // Act
        $response = $this->putJson("/api/reservas/{$reservaUsuario->id}", $data);

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Reserva actualizada exitosamente'
                 ]);
    }

    #[Test]
    public function puede_eliminar_reserva_propia()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $response = $this->deleteJson("/api/reservas/{$reserva->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Reserva eliminada exitosamente'
                 ]);

        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }

    #[Test]
    public function no_puede_eliminar_reserva_de_otro_usuario()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reservaOtroUsuario = Reserva::factory()->create(['usuario_id' => $this->admin->id]);

        // Act
        $response = $this->deleteJson("/api/reservas/{$reservaOtroUsuario->id}");

        // Assert
        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'No tienes permiso para eliminar esta reserva'
                 ]);
    }

    #[Test]
    public function puede_cambiar_estado_de_reserva()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reserva = Reserva::factory()->pendiente()->create(['usuario_id' => $this->user->id]);

        // Act
        $response = $this->putJson("/api/reservas/{$reserva->id}/estado", [
            'estado' => 'confirmada'
        ]);

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Estado de reserva actualizado exitosamente'
                 ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'estado' => 'confirmada'
        ]);
    }

    #[Test]
    public function valida_estados_validos_al_cambiar_estado()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);

        // Act
        $response = $this->putJson("/api/reservas/{$reserva->id}/estado", [
            'estado' => 'estado_invalido'
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['estado']);
    }

    #[Test]
    public function puede_obtener_reservas_por_emprendedor()
    {
        // Arrange
        Sanctum::actingAs($this->admin, ['*']);
        
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'emprendedor_id' => $this->emprendedor->id,
            'servicio_id' => $this->servicio->id
        ]);

        // Act
        $response = $this->getJson("/api/reservas/emprendedor/{$this->emprendedor->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);
                 
        $reservas = $response->json('data');
        $this->assertNotEmpty($reservas);
    }

    #[Test]
    public function puede_obtener_reservas_por_servicio()
    {
        // Arrange
        Sanctum::actingAs($this->admin, ['*']);
        
        $reserva = Reserva::factory()->create(['usuario_id' => $this->user->id]);
        ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $response = $this->getJson("/api/reservas/servicio/{$this->servicio->id}");

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);
    }

    #[Test]
    public function puede_crear_reserva_rapida_para_usuario_autenticado()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $data = [
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480,
                    'cantidad' => 1,
                    'notas_cliente' => 'Reserva rápida'
                ]
            ],
            'notas' => 'Reserva creada rápidamente'
        ];

        // Act
        $response = $this->postJson('/api/reservas/mis-reservas', $data);

        // Assert
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Reserva creada exitosamente'
                 ]);

        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_PENDIENTE
        ]);
    }

    #[Test]
    public function puede_obtener_mis_reservas()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $misReservas = Reserva::factory()->count(3)->create(['usuario_id' => $this->user->id]);
        $reservasOtros = Reserva::factory()->count(2)->create(['usuario_id' => $this->admin->id]);

        // Act
        $response = $this->getJson('/api/reservas/mis-reservas');

        // Assert
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);
                 
        $reservas = $response->json('data');
        $this->assertCount(3, $reservas);
        
        foreach ($reservas as $reserva) {
            $this->assertEquals($this->user->id, $reserva['usuario_id']);
        }
    }

    #[Test]
    public function valida_servicios_requeridos_en_reserva_rapida()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $data = [
            'notas' => 'Sin servicios'
        ];

        // Act
        $response = $this->postJson('/api/reservas/mis-reservas', $data);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['servicios']);
    }

    #[Test]
    public function valida_datos_de_servicio_en_reserva_rapida()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $data = [
            'servicios' => [
                [
                    'servicio_id' => 999, // No existe
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => 'fecha_invalida',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/reservas/mis-reservas', $data);

        // Assert
        $response->assertStatus(422);
    }

    #[Test]
    public function puede_crear_reserva_con_multiples_servicios()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        $servicio2 = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);
        
        $data = [
            'notas' => 'Reserva con múltiples servicios',
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '12:00:00',
                    'duracion_minutos' => 180,
                    'cantidad' => 1,
                    'precio' => 75.00
                ],
                [
                    'servicio_id' => $servicio2->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'hora_inicio' => '14:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 180,
                    'cantidad' => 2,
                    'precio' => 100.00
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/reservas', $data);

        // Assert
        $response->assertStatus(201);
        
        $reserva = Reserva::where('usuario_id', $this->user->id)->latest()->first();
        $this->assertCount(2, $reserva->servicios);
    }

    #[Test]
    public function puede_manejar_reservas_en_diferentes_estados()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $reservaPendiente = Reserva::factory()->pendiente()->create(['usuario_id' => $this->user->id]);
        $reservaConfirmada = Reserva::factory()->confirmada()->create(['usuario_id' => $this->user->id]);
        $reservaCancelada = Reserva::factory()->cancelada()->create(['usuario_id' => $this->user->id]);

        // Act
        $response = $this->getJson('/api/reservas');

        // Assert
        $response->assertStatus(200);
        $reservas = $response->json('data');
        
        $estados = array_column($reservas, 'estado');
        $this->assertContains('pendiente', $estados);
        $this->assertContains('confirmada', $estados);
        $this->assertContains('cancelada', $estados);
    }

    #[Test]
    public function maneja_errores_de_servidor_correctamente()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);

        // Act - Intentar obtener reserva que causará error
        $response = $this->getJson('/api/reservas/abc'); // ID no numérico

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function requiere_autenticacion_para_todas_las_operaciones()
    {
        // Act & Assert - Sin autenticación
        $this->getJson('/api/reservas')->assertStatus(401);
        $this->postJson('/api/reservas', [])->assertStatus(401);
        $this->putJson('/api/reservas/1', [])->assertStatus(401);
        $this->deleteJson('/api/reservas/1')->assertStatus(401);
        $this->getJson('/api/reservas/mis-reservas')->assertStatus(401);
    }

    #[Test]
    public function puede_crear_reserva_con_servicios_de_diferentes_tipos()
    {
        // Arrange
        Sanctum::actingAs($this->user, ['*']);
        
        $data = [
            'servicios' => [
                [
                    'servicio_id' => $this->servicio->id,
                    'emprendedor_id' => $this->emprendedor->id,
                    'fecha_inicio' => '2024-08-15',
                    'fecha_fin' => '2024-08-17', // Servicio de múltiples días
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'duracion_minutos' => 480,
                    'cantidad' => 3,
                    'notas_cliente' => 'Servicio de aventura familiar'
                ]
            ],
            'notas' => 'Paquete turístico completo'
        ];

        // Act
        $response = $this->postJson('/api/reservas', $data);

        // Assert
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id' => $this->servicio->id,
            'fecha_inicio' => '2024-08-15',
            'fecha_fin' => '2024-08-17',
            'cantidad' => 3
        ]);
    }
}