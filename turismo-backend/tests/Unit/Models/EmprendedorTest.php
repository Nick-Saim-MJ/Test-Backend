<?php

namespace Tests\Unit\Models;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Models\User;
use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmprendedorTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear asociación con municipalidad
        $municipalidad = Municipalidad::factory()->create();
        $this->asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);
    }

    #[Test]
    public function puede_crear_emprendedor_con_datos_validos()
    {
        // Arrange
        $data = [
            'nombre' => 'Restaurante El Lago',
            'tipo_servicio' => 'Restaurante',
            'descripcion' => 'Especialidad en trucha fresca del lago',
            'ubicacion' => 'Av. Principal 123, Capachica',
            'telefono' => '987654321',
            'email' => 'contacto@ellago.com',
            'categoria' => 'Gastronomía',
            'precio_rango' => 'S/ 50 - S/ 100',
            'metodos_pago' => ['efectivo', 'tarjeta_credito'],
            'capacidad_aforo' => 80,
            'numero_personas_atiende' => 25,
            'horario_atencion' => 'Lunes a Domingo: 11:00 AM - 10:00 PM',
            'idiomas_hablados' => ['español', 'inglés'],
            'certificaciones' => ['DIRCETUR', 'Certificado sanitario'],
            'opciones_acceso' => ['vehiculo_propio', 'transporte_publico'],
            'facilidades_discapacidad' => true,
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ];

        // Act
        $emprendedor = Emprendedor::create($data);

        // Assert
        $this->assertInstanceOf(Emprendedor::class, $emprendedor);
        $this->assertEquals($data['nombre'], $emprendedor->nombre);
        $this->assertEquals($data['categoria'], $emprendedor->categoria);
        $this->assertTrue($emprendedor->estado);
        $this->assertTrue($emprendedor->facilidades_discapacidad);
        $this->assertDatabaseHas('emprendedores', [
            'nombre' => $data['nombre'],
            'email' => $data['email']
        ]);
    }

    #[Test]
    public function fillable_permite_campos_correctos()
    {
        // Arrange
        $emprendedor = new Emprendedor();
        $data = [
            'nombre' => 'Test Emprendedor',
            'categoria' => 'Turismo',
            'estado' => true,
            'asociacion_id' => $this->asociacion->id,
            'campo_no_permitido' => 'no debe ser asignado'
        ];

        // Act
        $emprendedor->fill($data);

        // Assert
        $this->assertEquals('Test Emprendedor', $emprendedor->nombre);
        $this->assertEquals('Turismo', $emprendedor->categoria);
        $this->assertTrue($emprendedor->estado);
        $this->assertNull($emprendedor->campo_no_permitido);
    }

    #[Test]
    public function casts_convierte_tipos_correctamente()
    {
        // Arrange & Act
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'metodos_pago' => ['efectivo', 'tarjeta_credito'],
            'imagenes' => ['imagen1.jpg', 'imagen2.jpg'],
            'certificaciones' => ['CALTUR', 'DIRCETUR'],
            'idiomas_hablados' => ['español', 'inglés'],
            'opciones_acceso' => ['vehiculo_propio'],
            'facilidades_discapacidad' => '1', // String
            'estado' => '1' // String
        ]);

        // Assert
        $this->assertIsArray($emprendedor->metodos_pago);
        $this->assertIsArray($emprendedor->imagenes);
        $this->assertIsArray($emprendedor->certificaciones);
        $this->assertIsArray($emprendedor->idiomas_hablados);
        $this->assertIsArray($emprendedor->opciones_acceso);
        
        $this->assertIsBool($emprendedor->facilidades_discapacidad);
        $this->assertTrue($emprendedor->facilidades_discapacidad);
        
        $this->assertIsBool($emprendedor->estado);
        $this->assertTrue($emprendedor->estado);
    }

    #[Test]
    public function relacion_asociacion_funciona_correctamente()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $asociacionRelacionada = $emprendedor->asociacion;

        // Assert
        $this->assertInstanceOf(Asociacion::class, $asociacionRelacionada);
        $this->assertEquals($this->asociacion->id, $asociacionRelacionada->id);
        $this->assertEquals($this->asociacion->nombre, $asociacionRelacionada->nombre);
    }

    #[Test]
    public function relacion_administradores_funciona_correctamente()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $usuarios = User::factory()->count(3)->create();
        
        // Asociar usuarios como administradores
        $emprendedor->administradores()->attach([
            $usuarios[0]->id => ['es_principal' => true, 'rol' => 'administrador'],
            $usuarios[1]->id => ['es_principal' => false, 'rol' => 'administrador'],
            $usuarios[2]->id => ['es_principal' => false, 'rol' => 'colaborador']
        ]);

        // Act
        $administradoresRelacionados = $emprendedor->administradores;

        // Assert
        $this->assertCount(3, $administradoresRelacionados);
        foreach ($usuarios as $usuario) {
            $this->assertTrue(
                $administradoresRelacionados->contains('id', $usuario->id)
            );
        }
    }

    #[Test]
    public function relacion_administradores_incluye_timestamps_y_pivot()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $usuario = User::factory()->create();
        
        // Act
        $emprendedor->administradores()->attach($usuario->id, [
            'es_principal' => true,
            'rol' => 'administrador'
        ]);
        
        $administrador = $emprendedor->administradores()->first();
        $pivot = $administrador->pivot;

        // Assert
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
        $this->assertTrue($pivot->es_principal);
        $this->assertEquals('administrador', $pivot->rol);
    }

    #[Test]
    public function puede_obtener_administrador_principal()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $usuarioPrincipal = User::factory()->create();
        $usuarioSecundario = User::factory()->create();
        
        $emprendedor->administradores()->attach([
            $usuarioPrincipal->id => ['es_principal' => true, 'rol' => 'administrador'],
            $usuarioSecundario->id => ['es_principal' => false, 'rol' => 'colaborador']
        ]);

        // Act
        $administradorPrincipal = $emprendedor->administradorPrincipal();

        // Assert
        $this->assertInstanceOf(User::class, $administradorPrincipal);
        $this->assertEquals($usuarioPrincipal->id, $administradorPrincipal->id);
    }

    #[Test]
    public function relacion_servicios_funciona_correctamente()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        $servicios = Servicio::factory()->count(3)->create([
            'emprendedor_id' => $emprendedor->id
        ]);

        // Act
        $serviciosRelacionados = $emprendedor->servicios;

        // Assert
        $this->assertCount(3, $serviciosRelacionados);
        foreach ($servicios as $servicio) {
            $this->assertTrue(
                $serviciosRelacionados->contains('id', $servicio->id)
            );
        }
    }

    #[Test]
    public function relacion_sliders_existe_y_funciona()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $slidersRelation = $emprendedor->sliders();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $slidersRelation);
        $this->assertEquals('entidad_id', $slidersRelation->getForeignKeyName());
    }

    #[Test]
    public function relacion_sliders_principales_filtra_correctamente()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $slidersPrincipalesRelation = $emprendedor->slidersPrincipales();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $slidersPrincipalesRelation);
        
        // Verificar que la consulta incluye el filtro correcto
        $query = $slidersPrincipalesRelation->getQuery();
        $wheres = $query->wheres;
        
        $tieneFiltroPrincipal = false;
        foreach ($wheres as $where) {
            if (isset($where['column']) && $where['column'] === 'es_principal' && $where['value'] === true) {
                $tieneFiltroPrincipal = true;
                break;
            }
        }
        $this->assertTrue($tieneFiltroPrincipal);
    }

    #[Test]
    public function tabla_correcta_es_utilizada()
    {
        // Arrange
        $emprendedor = new Emprendedor();

        // Act
        $tabla = $emprendedor->getTable();

        // Assert
        $this->assertEquals('emprendedores', $tabla);
    }

    #[Test]
    public function primary_key_es_id_por_defecto()
    {
        // Arrange
        $emprendedor = new Emprendedor();

        // Act
        $primaryKey = $emprendedor->getKeyName();

        // Assert
        $this->assertEquals('id', $primaryKey);
    }

    #[Test]
    public function timestamps_estan_habilitados()
    {
        // Arrange
        $emprendedor = new Emprendedor();

        // Act
        $timestamps = $emprendedor->usesTimestamps();

        // Assert
        $this->assertTrue($timestamps);
    }

    #[Test]
    public function puede_actualizar_campos_individuales()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'nombre' => 'Nombre Original',
            'categoria' => 'Turismo'
        ]);

        // Act
        $emprendedor->update([
            'nombre' => 'Nombre Actualizado',
            'categoria' => 'Gastronomía'
        ]);

        // Assert
        $this->assertEquals('Nombre Actualizado', $emprendedor->fresh()->nombre);
        $this->assertEquals('Gastronomía', $emprendedor->fresh()->categoria);
    }

    #[Test]
    public function puede_eliminar_emprendedor()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $id = $emprendedor->id;

        // Act
        $result = $emprendedor->delete();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('emprendedores', ['id' => $id]);
    }

    #[Test]
    public function maneja_valores_nulos_correctamente()
    {
        // Arrange & Act
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'descripcion' => null,
            'telefono' => null,
            'email' => null,
            'pagina_web' => null,
            'comentarios_resenas' => null,
            'ubicacion' => null
        ]);

        // Assert
        $this->assertNull($emprendedor->descripcion);
        $this->assertNull($emprendedor->telefono);
        $this->assertNull($emprendedor->email);
        $this->assertNull($emprendedor->pagina_web);
        $this->assertNull($emprendedor->comentarios_resenas);
        $this->assertNull($emprendedor->ubicacion);
    }

    #[Test]
    public function created_at_y_updated_at_se_establecen_automaticamente()
    {
        // Arrange & Act
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Assert
        $this->assertNotNull($emprendedor->created_at);
        $this->assertNotNull($emprendedor->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emprendedor->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emprendedor->updated_at);
    }

    #[Test]
    public function arrays_se_almacenan_correctamente()
    {
        // Arrange & Act
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'metodos_pago' => ['efectivo', 'tarjeta_credito', 'yape'],
            'imagenes' => ['imagen1.jpg', 'imagen2.jpg', 'imagen3.jpg'],
            'certificaciones' => ['CALTUR', 'DIRCETUR'],
            'idiomas_hablados' => ['español', 'inglés', 'quechua'],
            'opciones_acceso' => ['vehiculo_propio', 'transporte_publico']
        ]);

        // Assert
        $this->assertEquals(['efectivo', 'tarjeta_credito', 'yape'], $emprendedor->metodos_pago);
        $this->assertEquals(['imagen1.jpg', 'imagen2.jpg', 'imagen3.jpg'], $emprendedor->imagenes);
        $this->assertEquals(['CALTUR', 'DIRCETUR'], $emprendedor->certificaciones);
        $this->assertEquals(['español', 'inglés', 'quechua'], $emprendedor->idiomas_hablados);
        $this->assertEquals(['vehiculo_propio', 'transporte_publico'], $emprendedor->opciones_acceso);
    }

    #[Test]
    public function facilidades_discapacidad_se_almacena_como_boolean()
    {
        // Arrange & Act
        $emprendedorConFacilidades = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'facilidades_discapacidad' => true
        ]);

        $emprendedorSinFacilidades = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'facilidades_discapacidad' => false
        ]);

        // Assert
        $this->assertIsBool($emprendedorConFacilidades->facilidades_discapacidad);
        $this->assertIsBool($emprendedorSinFacilidades->facilidades_discapacidad);
        $this->assertTrue($emprendedorConFacilidades->facilidades_discapacidad);
        $this->assertFalse($emprendedorSinFacilidades->facilidades_discapacidad);
    }

    #[Test]
    public function estado_se_almacena_como_boolean()
    {
        // Arrange & Act
        $emprendedorActivo = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'estado' => true
        ]);

        $emprendedorInactivo = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'estado' => false
        ]);

        // Assert
        $this->assertIsBool($emprendedorActivo->estado);
        $this->assertIsBool($emprendedorInactivo->estado);
        $this->assertTrue($emprendedorActivo->estado);
        $this->assertFalse($emprendedorInactivo->estado);
    }

    #[Test]
    public function puede_convertir_a_array()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $array = $emprendedor->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('nombre', $array);
        $this->assertArrayHasKey('categoria', $array);
        $this->assertArrayHasKey('estado', $array);
        $this->assertArrayHasKey('asociacion_id', $array);
        $this->assertArrayHasKey('metodos_pago', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    public function puede_convertir_a_json()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);

        // Act
        $json = $emprendedor->toJson();
        $data = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nombre', $data);
        $this->assertArrayHasKey('categoria', $data);
    }

    #[Test]
    public function capacidad_aforo_es_entero_positivo()
    {
        // Arrange & Act
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'capacidad_aforo' => 50
        ]);

        // Assert
        $this->assertIsInt($emprendedor->capacidad_aforo);
        $this->assertGreaterThan(0, $emprendedor->capacidad_aforo);
    }

    #[Test]
    public function numero_personas_atiende_es_entero_positivo()
    {
        // Arrange & Act
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id,
            'numero_personas_atiende' => 15
        ]);

        // Assert
        $this->assertIsInt($emprendedor->numero_personas_atiende);
        $this->assertGreaterThan(0, $emprendedor->numero_personas_atiende);
    }

    #[Test]
    public function puede_asociar_y_desasociar_administradores()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $usuario1 = User::factory()->create();
        $usuario2 = User::factory()->create();

        // Act - Asociar administradores
        $emprendedor->administradores()->attach([
            $usuario1->id => ['es_principal' => true, 'rol' => 'administrador'],
            $usuario2->id => ['es_principal' => false, 'rol' => 'colaborador']
        ]);
        
        // Assert - Verificar asociación
        $this->assertCount(2, $emprendedor->administradores);
        
        // Act - Desasociar un administrador
        $emprendedor->administradores()->detach($usuario1->id);
        
        // Assert - Verificar desasociación
        $emprendedor->refresh();
        $this->assertCount(1, $emprendedor->administradores);
        $this->assertTrue($emprendedor->administradores->contains('id', $usuario2->id));
        $this->assertFalse($emprendedor->administradores->contains('id', $usuario1->id));
    }

    #[Test]
    public function puede_sincronizar_administradores()
    {
        // Arrange
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $this->asociacion->id
        ]);
        $administradoresIniciales = User::factory()->count(3)->create();
        $nuevosAdministradores = User::factory()->count(2)->create();
        
        // Asociar administradores iniciales
        $datosIniciales = [];
        foreach ($administradoresIniciales as $admin) {
            $datosIniciales[$admin->id] = ['es_principal' => false, 'rol' => 'colaborador'];
        }
        $emprendedor->administradores()->attach($datosIniciales);

        // Act - Sincronizar con nuevos administradores
        $datosNuevos = [];
        foreach ($nuevosAdministradores as $index => $admin) {
            $datosNuevos[$admin->id] = [
                'es_principal' => $index === 0, // El primero será principal
                'rol' => 'administrador'
            ];
        }
        $emprendedor->administradores()->sync($datosNuevos);

        // Assert
        $emprendedor->refresh();
        $this->assertCount(2, $emprendedor->administradores);
        foreach ($nuevosAdministradores as $admin) {
            $this->assertTrue($emprendedor->administradores->contains('id', $admin->id));
        }
        foreach ($administradoresIniciales as $admin) {
            $this->assertFalse($emprendedor->administradores->contains('id', $admin->id));
        }
    }
}