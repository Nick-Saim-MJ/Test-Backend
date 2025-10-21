<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Categoria;
use App\Models\Asociacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

class CarritoReservaControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Servicio $servicio;
    protected Emprendedor $emprendedor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $asociacion = Asociacion::factory()->create();
        $categoria = Categoria::factory()->create();
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $this->servicio = Servicio::factory()->create([
            'emprendedor_id' => $this->emprendedor->id,
            'categoria_id' => $categoria->id,
            'precio_referencial' => 100.00,
        ]);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_get_empty_cart()
    {
        $response = $this->getJson('/api/reservas/carrito');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'usuario_id',
                        'codigo_reserva',
                        'estado',
                        'servicios',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'usuario_id' => $this->user->id,
                        'estado' => Reserva::ESTADO_EN_CARRITO,
                    ]
                ]);
    }

    /** @test */
    public function user_can_get_existing_cart()
    {
        // Crear un carrito existente
        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_EN_CARRITO,
        ]);

        $response = $this->getJson('/api/reservas/carrito');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $carrito->id,
                        'usuario_id' => $this->user->id,
                        'estado' => Reserva::ESTADO_EN_CARRITO,
                    ]
                ]);
    }

    /** @test */
    public function user_can_add_service_to_cart()
    {
        $serviceData = [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-07-01',
            'fecha_fin' => '2024-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'duracion_minutos' => 120,
            'cantidad' => 1,
            'notas_cliente' => 'Nota de prueba',
        ];

        $response = $this->postJson('/api/reservas/carrito/agregar', $serviceData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'servicios' => [
                            '*' => [
                                'id',
                                'servicio_id',
                                'emprendedor_id',
                                'fecha_inicio',
                                'fecha_fin',
                                'hora_inicio',
                                'hora_fin',
                                'cantidad',
                                'precio',
                                'estado',
                            ]
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Servicio agregado al carrito exitosamente',
                ]);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => ReservaServicio::ESTADO_EN_CARRITO,
            'notas_cliente' => 'Nota de prueba',
        ]);
    }

    /** @test */
    public function add_to_cart_validates_required_fields()
    {
        $response = $this->postJson('/api/reservas/carrito/agregar', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'servicio_id',
                    'emprendedor_id',
                    'fecha_inicio',
                    'hora_inicio',
                    'hora_fin',
                    'duracion_minutos',
                ]);
    }

    /** @test */
    public function add_to_cart_validates_service_exists()
    {
        $serviceData = [
            'servicio_id' => 999999,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-07-01',
            'fecha_fin' => '2024-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'duracion_minutos' => 120,
        ];

        $response = $this->postJson('/api/reservas/carrito/agregar', $serviceData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['servicio_id']);
    }

    /** @test */
    public function add_to_cart_validates_date_format()
    {
        $serviceData = [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => 'invalid-date',
            'fecha_fin' => '2024-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'duracion_minutos' => 120,
        ];

        $response = $this->postJson('/api/reservas/carrito/agregar', $serviceData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fecha_inicio']);
    }

    /** @test */
    public function add_to_cart_validates_time_format()
    {
        $serviceData = [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-07-01',
            'fecha_fin' => '2024-07-01',
            'hora_inicio' => 'invalid-time',
            'hora_fin' => '12:00:00',
            'duracion_minutos' => 120,
        ];

        $response = $this->postJson('/api/reservas/carrito/agregar', $serviceData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['hora_inicio']);
    }

    /** @test */
    public function user_can_remove_service_from_cart()
    {
        // Crear carrito con servicio
        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_EN_CARRITO,
        ]);

        $servicioCarrito = ReservaServicio::factory()->create([
            'reserva_id' => $carrito->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $response = $this->deleteJson("/api/reservas/carrito/servicio/{$servicioCarrito->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Servicio eliminado del carrito exitosamente',
                ]);

        $this->assertDatabaseMissing('reserva_servicios', [
            'id' => $servicioCarrito->id,
        ]);
    }

    /** @test */
    public function user_cannot_remove_nonexistent_service_from_cart()
    {
        $response = $this->deleteJson('/api/reservas/carrito/servicio/999999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Servicio no encontrado en el carrito',
                ]);
    }

    /** @test */
    public function user_cannot_remove_service_from_other_users_cart()
    {
        $otherUser = User::factory()->create();
        
        $carrito = Reserva::factory()->create([
            'usuario_id' => $otherUser->id,
            'estado' => Reserva::ESTADO_EN_CARRITO,
        ]);

        $servicioCarrito = ReservaServicio::factory()->create([
            'reserva_id' => $carrito->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $response = $this->deleteJson("/api/reservas/carrito/servicio/{$servicioCarrito->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este servicio',
                ]);
    }

    /** @test */
    public function user_can_confirm_cart()
    {
        // Crear carrito con servicio
        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_EN_CARRITO,
        ]);

        ReservaServicio::factory()->create([
            'reserva_id' => $carrito->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $response = $this->postJson('/api/reservas/carrito/confirmar', [
            'notas' => 'Reserva confirmada de prueba',
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Reserva creada exitosamente',
                ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $carrito->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas' => 'Reserva confirmada de prueba',
        ]);

        $this->assertDatabaseHas('reserva_servicios', [
            'reserva_id' => $carrito->id,
            'estado' => ReservaServicio::ESTADO_PENDIENTE,
        ]);
    }

    /** @test */
    public function cannot_confirm_empty_cart()
    {
        // Crear carrito vacío
        Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_EN_CARRITO,
        ]);

        $response = $this->postJson('/api/reservas/carrito/confirmar');

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'El carrito está vacío. Agregue servicios antes de confirmar.',
                ]);
    }

    /** @test */
    public function cannot_confirm_nonexistent_cart()
    {
        $response = $this->postJson('/api/reservas/carrito/confirmar');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se encontró un carrito de reservas',
                ]);
    }

    /** @test */
    public function user_can_empty_cart()
    {
        // Crear carrito con servicios
        $carrito = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_EN_CARRITO,
        ]);

        ReservaServicio::factory()->count(2)->create([
            'reserva_id' => $carrito->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => ReservaServicio::ESTADO_EN_CARRITO,
        ]);

        $response = $this->deleteJson('/api/reservas/carrito/vaciar');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Carrito vaciado exitosamente',
                ]);

        $this->assertDatabaseMissing('reserva_servicios', [
            'reserva_id' => $carrito->id,
        ]);
    }

    /** @test */
    public function cannot_empty_nonexistent_cart()
    {
        $response = $this->deleteJson('/api/reservas/carrito/vaciar');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se encontró un carrito de reservas',
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_cart_endpoints()
    {
        auth()->logout();

        $response = $this->getJson('/api/reservas/carrito');
        $response->assertStatus(401);

        $response = $this->postJson('/api/reservas/carrito/agregar', []);
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/reservas/carrito/servicio/1');
        $response->assertStatus(401);

        $response = $this->postJson('/api/reservas/carrito/confirmar');
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/reservas/carrito/vaciar');
        $response->assertStatus(401);
    }

    /** @test */
    public function add_to_cart_uses_service_reference_price()
    {
        $serviceData = [
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'fecha_inicio' => '2024-07-01',
            'fecha_fin' => '2024-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'duracion_minutos' => 120,
            'cantidad' => 1,
        ];

        $response = $this->postJson('/api/reservas/carrito/agregar', $serviceData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reserva_servicios', [
            'servicio_id' => $this->servicio->id,
            'precio' => $this->servicio->precio_referencial,
        ]);
    }

    /** @test */
    public function cannot_remove_service_from_confirmed_reservation()
    {
        // Crear reserva confirmada
        $reserva = Reserva::factory()->create([
            'usuario_id' => $this->user->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
        ]);

        $servicioReserva = ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'estado' => ReservaServicio::ESTADO_PENDIENTE,
        ]);

        $response = $this->deleteJson("/api/reservas/carrito/servicio/{$servicioReserva->id}");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Este servicio ya no está en el carrito',
                ]);
    }
}