<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\PlanInscripcion;
use App\Models\Plan;
use App\Models\User;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlanInscripcionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $this->plan = Plan::factory()->create([
            'emprendedor_id' => $emprendedor->id,
            'precio_total' => 200.00,
        ]);
    }

    /** @test */
    public function plan_inscripcion_can_be_created_with_factory()
    {
        $inscripcion = PlanInscripcion::factory()->create();

        $this->assertInstanceOf(PlanInscripcion::class, $inscripcion);
        $this->assertDatabaseHas('plan_inscripciones', [
            'id' => $inscripcion->id,
        ]);
    }

    /** @test */
    public function plan_inscripcion_has_fillable_attributes()
    {
        $fillable = [
            'plan_id', 'user_id', 'estado', 'notas', 'fecha_inscripcion',
            'fecha_inicio_plan', 'fecha_fin_plan', 'notas_usuario',
            'requerimientos_especiales', 'numero_participantes',
            'precio_pagado', 'metodo_pago', 'comentarios_adicionales'
        ];

        $inscripcion = new PlanInscripcion();

        $this->assertEquals($fillable, $inscripcion->getFillable());
    }

    /** @test */
    public function plan_inscripcion_casts_dates_and_decimal()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'fecha_inscripcion' => '2024-01-01 10:00:00',
            'fecha_inicio_plan' => '2024-02-01 09:00:00',
            'fecha_fin_plan' => '2024-02-03 18:00:00',
            'precio_pagado' => 150.50,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $inscripcion->fecha_inscripcion);
        $this->assertInstanceOf(\Carbon\Carbon::class, $inscripcion->fecha_inicio_plan);
        $this->assertInstanceOf(\Carbon\Carbon::class, $inscripcion->fecha_fin_plan);
        $this->assertEquals(150.50, $inscripcion->precio_pagado);
    }

    /** @test */
    public function plan_inscripcion_has_plan_relationship()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
        ]);

        $this->assertInstanceOf(Plan::class, $inscripcion->plan);
        $this->assertEquals($this->plan->id, $inscripcion->plan->id);
    }

    /** @test */
    public function plan_inscripcion_has_user_relationship()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $inscripcion->usuario);
        $this->assertInstanceOf(User::class, $inscripcion->user);
        $this->assertEquals($this->user->id, $inscripcion->usuario->id);
        $this->assertEquals($this->user->id, $inscripcion->user->id);
    }

    /** @test */
    public function plan_inscripcion_calculates_precio_total_with_participants()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'plan_id' => $this->plan->id,
            'numero_participantes' => 3,
        ]);

        $precioTotalCalculado = $inscripcion->precio_total_calculado;

        $this->assertEquals(600.00, $precioTotalCalculado); // 200 * 3
    }

    /** @test */
    public function plan_inscripcion_esta_activa_with_confirmed_status()
    {
        $inscripcionConfirmada = PlanInscripcion::factory()->confirmada()->create();
        $inscripcionEnProgreso = PlanInscripcion::factory()->enProgreso()->create();
        $inscripcionPendiente = PlanInscripcion::factory()->pendiente()->create();

        $this->assertTrue($inscripcionConfirmada->estaActiva());
        $this->assertTrue($inscripcionEnProgreso->estaActiva());
        $this->assertFalse($inscripcionPendiente->estaActiva());
    }

    /** @test */
    public function plan_inscripcion_puede_ser_cancelada_when_pending()
    {
        $inscripcionPendiente = PlanInscripcion::factory()->pendiente()->create([
            'fecha_inicio_plan' => now()->addDays(5),
        ]);

        $this->assertTrue($inscripcionPendiente->puedeSerCancelada());
    }

    /** @test */
    public function plan_inscripcion_cannot_be_cancelled_when_started()
    {
        $inscripcionIniciada = PlanInscripcion::factory()->enProgreso()->create([
            'fecha_inicio_plan' => now()->subDay(),
        ]);

        $this->assertFalse($inscripcionIniciada->puedeSerCancelada());
    }

    /** @test */
    public function plan_inscripcion_cannot_be_cancelled_when_completed()
    {
        $inscripcionCompletada = PlanInscripcion::factory()->completada()->create();

        $this->assertFalse($inscripcionCompletada->puedeSerCancelada());
    }

    /** @test */
    public function plan_inscripcion_calculates_dias_para_inicio()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'fecha_inicio_plan' => now()->addDays(5),
        ]);

        $this->assertEquals(5, $inscripcion->dias_para_inicio);
    }

    /** @test */
    public function plan_inscripcion_dias_para_inicio_is_negative_for_past_dates()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'fecha_inicio_plan' => now()->subDays(3),
        ]);

        $this->assertEquals(-3, $inscripcion->dias_para_inicio);
    }

    /** @test */
    public function plan_inscripcion_dias_para_inicio_is_null_when_no_date()
    {
        $inscripcion = PlanInscripcion::factory()->create([
            'fecha_inicio_plan' => null,
        ]);

        $this->assertNull($inscripcion->dias_para_inicio);
    }

    /** @test */
    public function plan_inscripcion_scope_activas_filters_correctly()
    {
        $confirmada = PlanInscripcion::factory()->confirmada()->create();
        $enProgreso = PlanInscripcion::factory()->enProgreso()->create();
        $pendiente = PlanInscripcion::factory()->pendiente()->create();
        $cancelada = PlanInscripcion::factory()->cancelada()->create();

        $activas = PlanInscripcion::activas()->get();

        $this->assertCount(2, $activas);
        $this->assertTrue($activas->contains($confirmada));
        $this->assertTrue($activas->contains($enProgreso));
        $this->assertFalse($activas->contains($pendiente));
        $this->assertFalse($activas->contains($cancelada));
    }

    /** @test */
    public function plan_inscripcion_scope_pendientes_filters_correctly()
    {
        $confirmada = PlanInscripcion::factory()->confirmada()->create();
        $pendiente = PlanInscripcion::factory()->pendiente()->create();

        $pendientes = PlanInscripcion::pendientes()->get();

        $this->assertCount(1, $pendientes);
        $this->assertTrue($pendientes->contains($pendiente));
        $this->assertFalse($pendientes->contains($confirmada));
    }

    /** @test */
    public function plan_inscripcion_scope_del_usuario_filters_correctly()
    {
        $inscripcionUser1 = PlanInscripcion::factory()->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        $inscripcionUser2 = PlanInscripcion::factory()->create(['user_id' => $otherUser->id]);

        $inscripcionesUser1 = PlanInscripcion::delUsuario($this->user->id)->get();

        $this->assertCount(1, $inscripcionesUser1);
        $this->assertTrue($inscripcionesUser1->contains($inscripcionUser1));
        $this->assertFalse($inscripcionesUser1->contains($inscripcionUser2));
    }

    /** @test */
    public function plan_inscripcion_scope_del_plan_filters_correctly()
    {
        $inscripcionPlan1 = PlanInscripcion::factory()->create(['plan_id' => $this->plan->id]);
        $otherPlan = Plan::factory()->create();
        $inscripcionPlan2 = PlanInscripcion::factory()->create(['plan_id' => $otherPlan->id]);

        $inscripcionesPlan1 = PlanInscripcion::delPlan($this->plan->id)->get();

        $this->assertCount(1, $inscripcionesPlan1);
        $this->assertTrue($inscripcionesPlan1->contains($inscripcionPlan1));
        $this->assertFalse($inscripcionesPlan1->contains($inscripcionPlan2));
    }

    /** @test */
    public function plan_inscripcion_scope_proximas_a_iniciar_filters_correctly()
    {
        $proximaInscripcion = PlanInscripcion::factory()->confirmada()->create([
            'fecha_inicio_plan' => now()->addDays(3),
        ]);
        
        $lejanaInscripcion = PlanInscripcion::factory()->confirmada()->create([
            'fecha_inicio_plan' => now()->addDays(10),
        ]);

        $proximasInscripciones = PlanInscripcion::proximasAIniciar(7)->get();

        $this->assertCount(1, $proximasInscripciones);
        $this->assertTrue($proximasInscripciones->contains($proximaInscripcion));
        $this->assertFalse($proximasInscripciones->contains($lejanaInscripcion));
    }

    /** @test */
    public function plan_inscripcion_scope_en_progreso_filters_correctly()
    {
        $enProgreso = PlanInscripcion::factory()->confirmada()->create([
            'fecha_inicio_plan' => now()->subDay(),
            'fecha_fin_plan' => now()->addDay(),
        ]);
        
        $futura = PlanInscripcion::factory()->confirmada()->create([
            'fecha_inicio_plan' => now()->addDays(5),
            'fecha_fin_plan' => now()->addDays(8),
        ]);

        $inscripcionesEnProgreso = PlanInscripcion::enProgreso()->get();

        $this->assertCount(1, $inscripcionesEnProgreso);
        $this->assertTrue($inscripcionesEnProgreso->contains($enProgreso));
        $this->assertFalse($inscripcionesEnProgreso->contains($futura));
    }

    /** @test */
    public function plan_inscripcion_validates_payment_methods()
    {
        $this->assertEquals('efectivo', PlanInscripcion::METODO_EFECTIVO);
        $this->assertEquals('transferencia', PlanInscripcion::METODO_TRANSFERENCIA);
        $this->assertEquals('tarjeta', PlanInscripcion::METODO_TARJETA);
        $this->assertEquals('yape', PlanInscripcion::METODO_YAPE);
        $this->assertEquals('plin', PlanInscripcion::METODO_PLIN);
    }

    /** @test */
    public function plan_inscripcion_validates_status_constants()
    {
        $this->assertEquals('pendiente', PlanInscripcion::ESTADO_PENDIENTE);
        $this->assertEquals('confirmada', PlanInscripcion::ESTADO_CONFIRMADA);
        $this->assertEquals('cancelada', PlanInscripcion::ESTADO_CANCELADA);
        $this->assertEquals('completada', PlanInscripcion::ESTADO_COMPLETADA);
        $this->assertEquals('en_progreso', PlanInscripcion::ESTADO_EN_PROGRESO);
    }
}