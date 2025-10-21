<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\Emprendedor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class EventoFactory extends Factory
{
    protected $model = Evento::class;

    public function definition(): array
    {
        // Tipos de eventos turísticos específicos para Capachica/Lago Titicaca
        $tiposEventos = [
            'Festival Cultural',
            'Ceremonia Tradicional',
            'Actividad Gastronómica',
            'Tour Temático',
            'Actividad Acuática',
            'Caminata Ecológica',
            'Evento Deportivo',
            'Celebración Comunitaria',
            'Taller Artesanal',
            'Observación Astronómica'
        ];

        // Idiomas principales
        $idiomas = [
            'Español',
            'Quechua',
            'Inglés',
            'Bilingüe (Español-Quechua)',
            'Trilingüe (Español-Quechua-Inglés)'
        ];

        // Coordenadas realistas del área de Capachica (Lago Titicaca)
        $coordenadaX = $this->faker->randomFloat(6, -69.9, -69.7); // Longitud
        $coordenadaY = $this->faker->randomFloat(6, -15.7, -15.5); // Latitud

        // Fechas futuras para eventos
        $fechaInicio = $this->faker->dateTimeBetween('now', '+6 months');
        $fechaFin = (clone $fechaInicio)->modify('+' . $this->faker->numberBetween(1, 7) . ' days');

        // Horarios realistas
        $horaInicio = $this->faker->randomElement([
            '06:00:00', '07:00:00', '08:00:00', '09:00:00', '10:00:00',
            '14:00:00', '15:00:00', '16:00:00', '17:00:00', '18:00:00'
        ]);
        
        $duracionHoras = $this->faker->numberBetween(1, 8);
        $horaInicioCarbon = Carbon::createFromFormat('H:i:s', $horaInicio);
        $horaFin = $horaInicioCarbon->addHours($duracionHoras)->format('H:i:s');

        // Nombres de eventos específicos para turismo en Capachica
        $nombresEventos = [
            'Festival de la Trucha',
            'Ceremonia del Inti Raymi Local',
            'Tour de Islas Flotantes',
            'Caminata al Mirador Llachón',
            'Degustación de Quinua Real',
            'Tejido Tradicional en Taquile',
            'Navegación en Totora',
            'Observación de Vicuñas',
            'Ritual de Pago a la Pachamama',
            'Festival de Música Andina',
            'Taller de Cerámica Ancestral',
            'Pesca Tradicional en Kayak',
            'Mercado de Productos Orgánicos',
            'Danza Folklórica Capachica',
            'Astronomía Andina',
            'Cocina Solar Comunitaria'
        ];

        // Qué llevar específico para eventos turísticos
        $queLevar = [
            'Ropa cómoda, protector solar, gorra, agua',
            'Abrigo, zapatos antideslizantes, cámara fotográfica',
            'Traje de baño, toalla, sandalias, bloqueador',
            'Ropa abrigada, linterna, snacks energéticos',
            'Cuaderno de notas, delantal, gorro de chef',
            'Materiales serán proporcionados por el organizador',
            'Ropa cómoda para actividades al aire libre',
            'Documentos de identidad, dinero en efectivo',
            'Materiales básicos de arte, ropa que se puede ensuciar',
            'Manta, bebida caliente en termo, abrigo nocturno'
        ];

        return [
            'nombre' => $this->faker->randomElement($nombresEventos),
            'descripcion' => $this->generarDescripcionEvento(),
            'tipo_evento' => $this->faker->randomElement($tiposEventos),
            'idioma_principal' => $this->faker->randomElement($idiomas),
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'hora_inicio' => $horaInicio,
            'fecha_fin' => $fechaFin->format('Y-m-d'),
            'hora_fin' => $horaFin,
            'duracion_horas' => $duracionHoras,
            'coordenada_x' => $coordenadaX,
            'coordenada_y' => $coordenadaY,
            'id_emprendedor' => Emprendedor::factory(),
            'que_llevar' => $this->faker->randomElement($queLevar),
        ];
    }

    /**
     * Generar descripción específica para eventos turísticos
     */
    private function generarDescripcionEvento(): string
    {
        $descripciones = [
            'Experimenta la auténtica cultura local del Lago Titicaca en este evento único que combina tradición y naturaleza.',
            'Únete a esta actividad especial donde conocerás las costumbres ancestrales de las comunidades de Capachica.',
            'Disfruta de una experiencia gastronómica inolvidable con productos frescos del lago y preparaciones tradicionales.',
            'Participa en esta aventura que te conectará con la naturaleza y la historia milenaria de los pueblos del altiplano.',
            'Descubre los secretos de la artesanía local mientras aprendes técnicas transmitidas de generación en generación.',
            'Vive la magia del Lago Titicaca navegando en embarcaciones tradicionales de totora con guías locales expertos.',
            'Conecta con la cosmovisión andina a través de ceremonias y rituales que honran a la Pachamama y los Apus.',
            'Explora los paisajes únicos del altiplano mientras degustar productos orgánicos cultivados por familias locales.'
        ];

        return $this->faker->randomElement($descripciones) . ' ' . 
               $this->faker->sentence(8) . ' ' . 
               $this->faker->sentence(6);
    }

    /**
     * Evento activo (fecha futura)
     */
    public function activo(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('+1 day', '+3 months');
        $fechaFin = (clone $fechaInicio)->modify('+' . $this->faker->numberBetween(1, 5) . ' days');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
        ]);
    }

    /**
     * Evento pasado (fecha anterior)
     */
    public function pasado(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('-6 months', '-1 day');
        $fechaFin = (clone $fechaInicio)->modify('+' . $this->faker->numberBetween(1, 3) . ' days');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
        ]);
    }

    /**
     * Evento de un día
     */
    public function unDia(): static
    {
        $fecha = $this->faker->dateTimeBetween('now', '+2 months');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fecha->format('Y-m-d'),
            'fecha_fin' => $fecha->format('Y-m-d'),
            'duracion_horas' => $this->faker->numberBetween(2, 8),
        ]);
    }

    /**
     * Evento de múltiples días
     */
    public function multipleDias(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('now', '+3 months');
        $fechaFin = (clone $fechaInicio)->modify('+' . $this->faker->numberBetween(3, 7) . ' days');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
            'duracion_horas' => $this->faker->numberBetween(6, 10),
        ]);
    }

    /**
     * Evento gastronómico
     */
    public function gastronomico(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => $this->faker->randomElement([
                'Festival de la Trucha del Titicaca',
                'Degustación de Quinua Real',
                'Cocina Tradicional Altiplánica',
                'Taller de Chuño y Papa Nativa',
                'Preparación de Chicha de Quinua'
            ]),
            'tipo_evento' => 'Actividad Gastronómica',
            'duracion_horas' => $this->faker->numberBetween(3, 6),
            'que_llevar' => 'Delantal, gorro de chef, recipientes para llevar',
        ]);
    }

    /**
     * Evento cultural
     */
    public function cultural(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => $this->faker->randomElement([
                'Ceremonia del Inti Raymi',
                'Festival de Música Andina',
                'Danza Folklórica de Capachica',
                'Ritual de Pago a la Pachamama',
                'Tejido Tradicional en Taquile'
            ]),
            'tipo_evento' => 'Festival Cultural',
            'idioma_principal' => 'Bilingüe (Español-Quechua)',
            'duracion_horas' => $this->faker->numberBetween(4, 8),
            'que_llevar' => 'Ropa tradicional (opcional), cámara fotográfica',
        ]);
    }

    /**
     * Evento de aventura
     */
    public function aventura(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => $this->faker->randomElement([
                'Caminata al Mirador Llachón',
                'Navegación en Totora',
                'Pesca Tradicional en Kayak',
                'Trekking Isla del Sol',
                'Ciclismo de Montaña Altiplánica'
            ]),
            'tipo_evento' => 'Actividad Acuática',
            'duracion_horas' => $this->faker->numberBetween(4, 8),
            'que_llevar' => 'Ropa deportiva, zapatos antideslizantes, protector solar, agua',
        ]);
    }

    /**
     * Evento con emprendedor específico
     */
    public function conEmprendedor(int $emprendedorId): static
    {
        return $this->state(fn (array $attributes) => [
            'id_emprendedor' => $emprendedorId,
        ]);
    }

    /**
     * Evento matutino
     */
    public function matutino(): static
    {
        $horaInicio = $this->faker->randomElement(['06:00:00', '07:00:00', '08:00:00', '09:00:00']);
        $duracion = $this->faker->numberBetween(2, 4);
        $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)->addHours($duracion)->format('H:i:s');

        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_horas' => $duracion,
        ]);
    }

    /**
     * Evento vespertino
     */
    public function vespertino(): static
    {
        $horaInicio = $this->faker->randomElement(['14:00:00', '15:00:00', '16:00:00', '17:00:00']);
        $duracion = $this->faker->numberBetween(3, 6);
        $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)->addHours($duracion)->format('H:i:s');

        return $this->state(fn (array $attributes) => [
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_horas' => $duracion,
        ]);
    }

    /**
     * Evento con coordenadas específicas
     */
    public function enUbicacion(float $latitud, float $longitud): static
    {
        return $this->state(fn (array $attributes) => [
            'coordenada_x' => $longitud,
            'coordenada_y' => $latitud,
        ]);
    }
}