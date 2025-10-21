<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Slider;
use App\Models\SliderDescripcion;
use App\Models\Emprendedor;
use App\Models\Servicio;
use App\Models\Evento;
use App\Models\Asociacion;
use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class SliderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function slider_can_be_created_with_factory()
    {
        $slider = Slider::factory()->create();

        $this->assertInstanceOf(Slider::class, $slider);
        $this->assertDatabaseHas('sliders', [
            'id' => $slider->id,
            'nombre' => $slider->nombre,
        ]);
    }

    /** @test */
    public function slider_has_fillable_attributes()
    {
        $fillable = [
            'url', 'nombre', 'es_principal', 'tipo_entidad', 
            'entidad_id', 'orden', 'activo'
        ];

        $slider = new Slider();

        $this->assertEquals($fillable, $slider->getFillable());
    }

    /** @test */
    public function slider_casts_boolean_attributes()
    {
        $slider = Slider::factory()->create([
            'es_principal' => 1,
            'activo' => 1,
        ]);

        $this->assertIsBool($slider->es_principal);
        $this->assertIsBool($slider->activo);
    }

    /** @test */
    public function slider_has_descripcion_relationship()
    {
        $slider = Slider::factory()->create();
        $descripcion = SliderDescripcion::factory()->create(['slider_id' => $slider->id]);

        $this->assertInstanceOf(SliderDescripcion::class, $slider->descripcion);
        $this->assertEquals($descripcion->id, $slider->descripcion->id);
    }

    /** @test */
    public function slider_can_have_emprendedor_as_entidad()
    {
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        
        $slider = Slider::factory()->paraEmprendedor()->create([
            'tipo_entidad' => Emprendedor::class,
            'entidad_id' => $emprendedor->id,
        ]);

        $this->assertEquals(Emprendedor::class, $slider->tipo_entidad);
        $this->assertEquals($emprendedor->id, $slider->entidad_id);
        $this->assertInstanceOf(Emprendedor::class, $slider->entidad);
    }

    /** @test */
    public function slider_can_have_servicio_as_entidad()
    {
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $categoria = Categoria::factory()->create();
        $servicio = Servicio::factory()->create([
            'emprendedor_id' => $emprendedor->id,
            'categoria_id' => $categoria->id,
        ]);
        
        $slider = Slider::factory()->paraServicio()->create([
            'tipo_entidad' => Servicio::class,
            'entidad_id' => $servicio->id,
        ]);

        $this->assertEquals(Servicio::class, $slider->tipo_entidad);
        $this->assertEquals($servicio->id, $slider->entidad_id);
        $this->assertInstanceOf(Servicio::class, $slider->entidad);
    }

    /** @test */
    public function slider_can_have_evento_as_entidad()
    {
        $evento = Evento::factory()->create();
        
        $slider = Slider::factory()->paraEvento()->create([
            'tipo_entidad' => Evento::class,
            'entidad_id' => $evento->id,
        ]);

        $this->assertEquals(Evento::class, $slider->tipo_entidad);
        $this->assertEquals($evento->id, $slider->entidad_id);
        $this->assertInstanceOf(Evento::class, $slider->entidad);
    }

    /** @test */
    public function slider_can_be_principal()
    {
        $slider = Slider::factory()->principal()->create();

        $this->assertTrue($slider->es_principal);
        $this->assertEquals(1, $slider->orden);
    }

    /** @test */
    public function slider_can_be_inactive()
    {
        $slider = Slider::factory()->inactivo()->create();

        $this->assertFalse($slider->activo);
    }

    /** @test */
    public function slider_generates_url_completa_for_relative_url()
    {
        $slider = Slider::factory()->create([
            'url' => 'sliders/test-image.jpg'
        ]);

        $urlCompleta = $slider->url_completa;

        $this->assertStringContains('sliders/test-image.jpg', $urlCompleta);
        $this->assertStringStartsWith('http', $urlCompleta);
    }

    /** @test */
    public function slider_returns_absolute_url_when_url_is_complete()
    {
        $absoluteUrl = 'https://example.com/image.jpg';
        $slider = Slider::factory()->create([
            'url' => $absoluteUrl
        ]);

        $this->assertEquals($absoluteUrl, $slider->url_completa);
    }

    /** @test */
    public function slider_url_completa_is_appended_to_array()
    {
        $slider = Slider::factory()->create();

        $array = $slider->toArray();

        $this->assertArrayHasKey('url_completa', $array);
        $this->assertNotNull($array['url_completa']);
    }

    /** @test */
    public function slider_without_entidad_has_null_polymorphic_fields()
    {
        $slider = Slider::factory()->create([
            'tipo_entidad' => null,
            'entidad_id' => null,
        ]);

        $this->assertNull($slider->tipo_entidad);
        $this->assertNull($slider->entidad_id);
        $this->assertNull($slider->entidad);
    }

    /** @test */
    public function multiple_sliders_can_have_different_orden()
    {
        $slider1 = Slider::factory()->create(['orden' => 1]);
        $slider2 = Slider::factory()->create(['orden' => 2]);
        $slider3 = Slider::factory()->create(['orden' => 3]);

        $this->assertEquals(1, $slider1->orden);
        $this->assertEquals(2, $slider2->orden);
        $this->assertEquals(3, $slider3->orden);
    }

    /** @test */
    public function slider_can_have_descripcion_with_titulo_and_text()
    {
        $slider = Slider::factory()->create();
        $descripcion = SliderDescripcion::factory()->create([
            'slider_id' => $slider->id,
            'titulo' => 'Título del Slider',
            'descripcion' => 'Descripción detallada del slider',
        ]);

        $this->assertEquals('Título del Slider', $slider->descripcion->titulo);
        $this->assertEquals('Descripción detallada del slider', $slider->descripcion->descripcion);
    }
}