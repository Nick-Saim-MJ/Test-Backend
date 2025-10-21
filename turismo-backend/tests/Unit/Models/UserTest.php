<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Emprendedor;
use App\Models\Plan;
use App\Models\PlanInscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear roles básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);
        
        Storage::fake('public');
    }

    /** @test */
    public function user_can_be_created_with_factory()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /** @test */
    public function user_has_fillable_attributes()
    {
        $fillable = [
            'name', 'email', 'password', 'phone', 'active', 'foto_perfil',
            'google_id', 'avatar', 'country', 'birth_date', 'address',
            'gender', 'preferred_language', 'last_login'
        ];

        $user = new User();

        $this->assertEquals($fillable, $user->getFillable());
    }

    /** @test */
    public function user_has_hidden_attributes()
    {
        $hidden = ['password', 'remember_token', 'google_id'];

        $user = new User();

        $this->assertEquals($hidden, $user->getHidden());
    }

    /** @test */
    public function user_casts_attributes_correctly()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2023-01-01 12:00:00',
            'active' => 1,
            'birth_date' => '1990-01-01',
            'last_login' => '2023-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);
        $this->assertIsBool($user->active);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->birth_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->last_login);
    }

    /** @test */
    public function user_has_emprendimientos_relationship()
    {
        $user = User::factory()->create();
        $emprendedor = Emprendedor::factory()->create();
        
        $user->emprendimientos()->attach($emprendedor->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);

        $this->assertTrue($user->emprendimientos->contains($emprendedor));
        $this->assertEquals('administrador', $user->emprendimientos->first()->pivot->rol);
    }

    /** @test */
    public function user_can_check_if_administra_emprendimientos()
    {
        $user = User::factory()->create();
        $emprendedor = Emprendedor::factory()->create();

        // Sin emprendimientos
        $this->assertFalse($user->administraEmprendimientos());

        // Con emprendimientos
        $user->emprendimientos()->attach($emprendedor->id);
        $user->refresh();
        
        $this->assertTrue($user->administraEmprendimientos());
    }

    /** @test */
    public function user_has_planes_creados_relationship()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['creado_por_usuario_id' => $user->id]);

        $this->assertTrue($user->planesCreados->contains($plan));
    }

    /** @test */
    public function user_has_inscripciones_relationship()
    {
        $user = User::factory()->create();
        $inscripcion = PlanInscripcion::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->inscripciones->contains($inscripcion));
    }

    /** @test */
    public function user_has_planes_inscritos_relationship()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        
        PlanInscripcion::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'estado' => 'confirmada'
        ]);

        $this->assertTrue($user->planesInscritos->contains($plan));
    }

    /** @test */
    public function user_can_get_inscripciones_activas()
    {
        $user = User::factory()->create();
        
        $inscripcionConfirmada = PlanInscripcion::factory()->confirmada()->create(['user_id' => $user->id]);
        $inscripcionEnProgreso = PlanInscripcion::factory()->enProgreso()->create(['user_id' => $user->id]);
        $inscripcionPendiente = PlanInscripcion::factory()->pendiente()->create(['user_id' => $user->id]);

        $activas = $user->inscripcionesActivas;

        $this->assertCount(2, $activas);
        $this->assertTrue($activas->contains($inscripcionConfirmada));
        $this->assertTrue($activas->contains($inscripcionEnProgreso));
        $this->assertFalse($activas->contains($inscripcionPendiente));
    }

    /** @test */
    public function user_can_get_inscripciones_pendientes()
    {
        $user = User::factory()->create();
        
        $inscripcionPendiente = PlanInscripcion::factory()->pendiente()->create(['user_id' => $user->id]);
        $inscripcionConfirmada = PlanInscripcion::factory()->confirmada()->create(['user_id' => $user->id]);

        $pendientes = $user->inscripcionesPendientes;

        $this->assertCount(1, $pendientes);
        $this->assertTrue($pendientes->contains($inscripcionPendiente));
        $this->assertFalse($pendientes->contains($inscripcionConfirmada));
    }

    /** @test */
    public function user_can_get_proximas_inscripciones()
    {
        $user = User::factory()->create();
        
        // Inscripción que inicia en 5 días
        $inscripcionProxima = PlanInscripcion::factory()->confirmada()->create([
            'user_id' => $user->id,
            'fecha_inicio_plan' => now()->addDays(5)
        ]);
        
        // Inscripción que inicia en 2 meses
        $inscripcionLejana = PlanInscripcion::factory()->confirmada()->create([
            'user_id' => $user->id,
            'fecha_inicio_plan' => now()->addMonths(2)
        ]);

        $proximas = $user->proximasInscripciones(30);

        $this->assertCount(1, $proximas);
        $this->assertTrue($proximas->contains($inscripcionProxima));
        $this->assertFalse($proximas->contains($inscripcionLejana));
    }

    /** @test */
    public function user_can_get_inscripciones_en_progreso()
    {
        $user = User::factory()->create();
        
        $inscripcionEnProgreso = PlanInscripcion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'confirmada',
            'fecha_inicio_plan' => now()->subDays(2),
            'fecha_fin_plan' => now()->addDays(2)
        ]);
        
        $inscripcionFutura = PlanInscripcion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'confirmada',
            'fecha_inicio_plan' => now()->addDays(5),
            'fecha_fin_plan' => now()->addDays(8)
        ]);

        $enProgreso = $user->inscripcionesEnProgreso;

        $this->assertCount(1, $enProgreso);
        $this->assertTrue($enProgreso->contains($inscripcionEnProgreso));
        $this->assertFalse($enProgreso->contains($inscripcionFutura));
    }

    /** @test */
    public function user_can_check_if_puede_gestionar_plan()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['creado_por_usuario_id' => $user->id]);

        $this->assertTrue($user->puedeGestionarPlan($plan->id));
        
        $otherUser = User::factory()->create();
        $this->assertFalse($otherUser->puedeGestionarPlan($plan->id));
    }

    /** @test */
    public function admin_user_can_gestionar_any_plan()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $plan = Plan::factory()->create();

        $this->assertTrue($admin->puedeGestionarPlan($plan->id));
    }

    /** @test */
    public function user_gets_estadisticas_planes_correctly()
    {
        $user = User::factory()->create();
        
        // Crear planes creados por el usuario
        Plan::factory()->count(2)->create(['creado_por_usuario_id' => $user->id]);
        
        // Crear inscripciones
        PlanInscripcion::factory()->confirmada()->create(['user_id' => $user->id, 'precio_pagado' => 100]);
        PlanInscripcion::factory()->pendiente()->create(['user_id' => $user->id]);

        $estadisticas = $user->estadisticas_planes_usuario;

        $this->assertEquals(2, $estadisticas['planes_creados']);
        $this->assertEquals(2, $estadisticas['total_inscripciones']);
        $this->assertEquals(1, $estadisticas['inscripciones_confirmadas']);
        $this->assertEquals(1, $estadisticas['inscripciones_pendientes']);
        $this->assertEquals(100, $estadisticas['total_gastado']);
    }

    /** @test */
    public function user_can_check_tiene_inscripciones_proximas()
    {
        $user = User::factory()->create();
        
        PlanInscripcion::factory()->confirmada()->create([
            'user_id' => $user->id,
            'fecha_inicio_plan' => now()->addDays(3)
        ]);

        $this->assertTrue($user->tieneInscripcionesProximas(7));
        $this->assertFalse($user->tieneInscripcionesProximas(2));
    }

    /** @test */
    public function user_can_check_tiene_inscripciones_en_progreso()
    {
        $user = User::factory()->create();
        
        PlanInscripcion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'confirmada',
            'fecha_inicio_plan' => now()->subDay(),
            'fecha_fin_plan' => now()->addDay()
        ]);

        $this->assertTrue($user->tieneInscripcionesEnProgreso());
    }

    /** @test */
    public function user_gets_foto_perfil_url_correctly()
    {
        // Sin foto ni avatar
        $user = User::factory()->create([
            'foto_perfil' => null,
            'avatar' => null
        ]);
        $this->assertNull($user->foto_perfil_url);

        // Con avatar de Google
        $user = User::factory()->withGoogle()->create([
            'foto_perfil' => null,
            'avatar' => 'https://example.com/avatar.jpg'
        ]);
        $this->assertEquals('https://example.com/avatar.jpg', $user->foto_perfil_url);

        // Con foto de perfil (prioridad sobre avatar)
        $user = User::factory()->create([
            'foto_perfil' => 'fotos_perfil/test.jpg',
            'avatar' => 'https://example.com/avatar.jpg'
        ]);
        $this->assertNotNull($user->foto_perfil_url);
        $this->assertStringContains('fotos_perfil/test.jpg', $user->foto_perfil_url);
    }

    /** @test */
    public function user_can_check_registered_with_google()
    {
        $normalUser = User::factory()->create(['google_id' => null]);
        $googleUser = User::factory()->withGoogle()->create();

        $this->assertFalse($normalUser->registeredWithGoogle());
        $this->assertTrue($googleUser->registeredWithGoogle());
    }
}