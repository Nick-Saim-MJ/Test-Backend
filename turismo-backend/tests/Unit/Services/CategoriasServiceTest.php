<?php
namespace Tests\Unit\Services;

use App\Models\Categoria;
use App\Models\Servicio;
use App\Services\CategoriasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CategoriasServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected CategoriasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoriasService();
    }

    #[Test]
    public function puede_obtener_todas_las_categorias_paginadas()
    {
        // Arrange
        Categoria::factory()->count(20)->create();

        // Act
        $result = $this->service->getAll(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(10, $result->items());
    }

    #[Test]
    public function puede_obtener_todas_las_categorias_sin_paginacion()
    {
        // Arrange
        Categoria::factory()->count(5)->create();

        // Act
        $result = $this->service->getAll();

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(15, $result->perPage()); // Valor por defecto
        $this->assertEquals(5, $result->total());
        $this->assertCount(5, $result->items());
    }

    #[Test]
    public function puede_obtener_una_categoria_por_id()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $result = $this->service->getById($categoria->id);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertEquals($categoria->id, $result->id);
        $this->assertEquals($categoria->nombre, $result->nombre);
    }

    #[Test]
    public function retorna_null_cuando_categoria_no_existe()
    {
        // Act
        $result = $this->service->getById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_obtener_categoria_con_servicios()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $servicios = Servicio::factory()->count(3)->create();
        $categoria->servicios()->attach($servicios->pluck('id'));

        // Act
        $result = $this->service->getWithServicios($categoria->id);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertTrue($result->relationLoaded('servicios'));
        $this->assertCount(3, $result->servicios);
    }

    #[Test]
    public function retorna_null_al_obtener_categoria_con_servicios_inexistente()
    {
        // Act
        $result = $this->service->getWithServicios(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_nueva_categoria()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text,
            'icono_url' => $this->faker->imageUrl()
        ];

        // Act
        $result = $this->service->create($data);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['descripcion'], $result->descripcion);
        $this->assertEquals($data['icono_url'], $result->icono_url);
        $this->assertDatabaseHas('categorias', $data);
    }

    #[Test]
    public function puede_actualizar_categoria_existente()
    {
        // Arrange
        $categoria = Categoria::factory()->create();
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text,
            'icono_url' => $this->faker->imageUrl()
        ];

        // Act
        $result = $this->service->update($categoria->id, $data);

        // Assert
        $this->assertInstanceOf(Categoria::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
        $this->assertEquals($data['descripcion'], $result->descripcion);
        $this->assertEquals($data['icono_url'], $result->icono_url);
        $this->assertDatabaseHas('categorias', array_merge(['id' => $categoria->id], $data));
    }

    #[Test]
    public function retorna_null_al_actualizar_categoria_inexistente()
    {
        // Arrange
        $data = [
            'nombre' => $this->faker->word,
            'descripcion' => $this->faker->text
        ];

        // Act
        $result = $this->service->update(999, $data);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_eliminar_categoria_existente()
    {
        // Arrange
        $categoria = Categoria::factory()->create();

        // Act
        $result = $this->service->delete($categoria->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_categoria_inexistente()
    {
        // Act
        $result = $this->service->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_buscar_categorias_por_nombre()
    {
        // Arrange
        $categoria1 = Categoria::factory()->create(['nombre' => 'Tecnología']);
        $categoria2 = Categoria::factory()->create(['nombre' => 'Salud']);
        $categoria3 = Categoria::factory()->create(['nombre' => 'Tecnología Médica']);

        // Act
        $result = $this->service->search('Tecnología');

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(2, $result->total());
        $nombres = $result->pluck('nombre')->toArray();
        $this->assertContains('Tecnología', $nombres);
        $this->assertContains('Tecnología Médica', $nombres);
        $this->assertNotContains('Salud', $nombres);
    }

    #[Test]
    public function puede_obtener_categorias_activas_solamente()
    {
        // Arrange
        Categoria::factory()->count(3)->create(['activo' => true]);
        Categoria::factory()->count(2)->create(['activo' => false]);

        // Act
        $result = $this->service->getActive();

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->total());
        foreach ($result->items() as $categoria) {
            $this->assertTrue($categoria->activo);
        }
    }
}