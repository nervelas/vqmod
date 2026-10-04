<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Datos de los presets por profesión (editables). Solo contiene datos y pequeños constructores;
 * la lógica de aplicarlos vive en ProfessionService.
 *
 * Marcadores de texto: [cita] y [Cita] se sustituyen por el término de la profesión (consulta, sesión, reunión…).
 * Enrutamiento: cada opción de la pregunta lleva a la clave de un evento del mismo preset.
 */
final class ProfessionPresets
{
    private const GOLD = '#C9A050';
    private const BRASS = '#8E6F3E';
    private const SAND = '#B08D57';
    private const SLATE = '#5B7C99';
    private const SAGE = '#7A8F6A';
    private const ROSE = '#A0646E';
    private const PLUM = '#6B5B95';
    private const TEAL = '#4F8A8B';

    /** Constructor de evento sugerido. $o admite: default, buffer_before, buffer_after, approval, cancel_hours, cancel, deposit [tipo, valor], notice, interval, travel, kind, capacity, confirm, location, guests. */
    private static function ev(string $key, string $name, string $desc, array $durations, float $price, string $mode, string $color, array $o = []): array
    {
        return ['key' => $key, 'name' => $name, 'description' => $desc, 'durations' => $durations, 'price' => $price, 'mode' => $mode, 'color' => $color] + $o;
    }

    private static function f(string $name, string $label, string $type, array $o = []): array
    {
        return ['name' => $name, 'label' => $label, 'type' => $type] + $o;
    }

    public static function all(): array
    {
        return [
            'medico' => [
                'name' => 'Médico y clínica', 'icon' => 'shield',
                'short' => 'Consultas, controles, teleconsulta y chequeos para consultorios y clínicas.',
                'terms' => ['cita' => 'consulta', 'plural' => 'consultas', 'host' => 'doctor(a)', 'client' => 'paciente'],
                'events' => [
                    self::ev('consulta-medica-general', 'Consulta médica general', 'Evaluación de síntomas, diagnóstico y tratamiento. Si ya tienes exámenes recientes, tráelos.', [30, 45], 300, 'in_person', self::GOLD, ['buffer_after' => 10, 'cancel_hours' => 12]),
                    self::ev('primera-consulta-medica', 'Primera consulta con historia clínica', 'Entrevista completa, revisión de antecedentes y examen físico. Recomendamos llegar 10 minutos antes.', [60], 450, 'in_person', self::BRASS, ['buffer_after' => 10, 'cancel_hours' => 24]),
                    self::ev('control-seguimiento-medico', 'Control y seguimiento', 'Revisión de resultados y ajuste de tu tratamiento.', [20, 30], 200, 'in_person', self::SAND, ['buffer_after' => 5, 'cancel_hours' => 6]),
                    self::ev('teleconsulta-medica', 'Teleconsulta por videollamada', 'Consulta a distancia para controles y dudas que no requieren examen físico. Te enviamos el enlace por correo.', [30], 250, 'video_auto', self::SLATE, ['cancel_hours' => 6]),
                    self::ev('chequeo-medico-ejecutivo', 'Chequeo médico ejecutivo', 'Evaluación integral con revisión de laboratorios y recomendaciones personalizadas. Requiere ayuno de 8 horas.', [90], 1200, 'in_person', self::TEAL, ['approval' => 1, 'deposit' => ['fixed', 300], 'cancel_hours' => 48, 'confirm' => 'Recibimos tu solicitud. Te confirmaremos por correo y WhatsApp las indicaciones de ayuno y los laboratorios previos.']),
                ],
                'fields' => [
                    self::f('primera_visita', '¿Es su primera visita?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('motivo', '¿Cuál es el motivo de la consulta?', 'textarea', ['required' => 1, 'help' => 'Cuéntenos brevemente qué siente o qué necesita revisar.']),
                    self::f('seguro', '¿Cómo cubrirá la consulta?', 'select', ['options' => ['Particular', 'Seguro médico privado', 'IGSS'], 'required' => 1]),
                    self::f('aseguradora', '¿Con qué aseguradora?', 'text', ['condition' => ['seguro', 'Seguro médico privado'], 'help' => 'Indique la aseguradora y el número de póliza.']),
                    self::f('alergias', '¿Tiene alergias a medicamentos?', 'text', ['condition' => ['primera_visita', 'Sí'], 'help' => 'Si no tiene, escriba «Ninguna».']),
                    self::f('examenes', 'Adjunte sus exámenes recientes (opcional)', 'file', ['condition' => ['primera_visita', 'No'], 'help' => 'PDF o foto legible.']),
                ],
                'routing' => ['key' => 'tipo', 'label' => '¿Qué necesita?', 'default' => 'consulta-medica-general', 'options' => [
                    'Es mi primera vez con el doctor' => 'primera-consulta-medica',
                    'Control de una consulta anterior' => 'control-seguimiento-medico',
                    'No puedo ir al consultorio' => 'teleconsulta-medica',
                    'Quiero un chequeo completo' => 'chequeo-medico-ejecutivo',
                ]],
            ],
            'dentista' => [
                'name' => 'Dentista y clínica dental', 'icon' => 'sparkle',
                'short' => 'Limpiezas, valoraciones, ortodoncia y urgencias dentales.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'doctor(a)', 'client' => 'paciente'],
                'events' => [
                    self::ev('valoracion-dental', 'Valoración dental', 'Revisión completa, diagnóstico y plan de tratamiento con presupuesto.', [30], 150, 'in_person', self::GOLD, ['buffer_after' => 10]),
                    self::ev('limpieza-dental', 'Limpieza dental profunda', 'Profilaxis con ultrasonido y pulido. Incluye recomendaciones de higiene.', [45], 350, 'in_person', self::SAND, ['buffer_after' => 10, 'cancel_hours' => 12]),
                    self::ev('ortodoncia-control', 'Control de ortodoncia', 'Ajuste de aparatos y revisión del avance de tu tratamiento.', [20], 250, 'in_person', self::PLUM, ['buffer_after' => 5, 'cancel_hours' => 12]),
                    self::ev('blanqueamiento-dental', 'Blanqueamiento dental', 'Sesión de blanqueamiento en clínica. Requiere una limpieza previa reciente.', [60, 90], 1400, 'in_person', self::TEAL, ['deposit' => ['percent', 30], 'cancel_hours' => 48, 'buffer_after' => 15]),
                    self::ev('urgencia-dental', 'Urgencia dental', 'Dolor intenso, golpe o pieza rota. Te atendemos lo antes posible.', [30], 250, 'in_person', self::ROSE, ['notice' => 30, 'cancel_hours' => 2]),
                ],
                'fields' => [
                    self::f('primera_visita', '¿Es su primera visita a la clínica?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('motivo', '¿Qué molestia o tratamiento le interesa?', 'textarea', ['required' => 1]),
                    self::f('dolor', '¿Siente dolor actualmente?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('dolor_desde', '¿Desde cuándo tiene el dolor?', 'text', ['condition' => ['dolor', 'Sí']]),
                    self::f('alergias', '¿Es alérgico(a) a algún medicamento, como penicilina o anestesia?', 'text', ['condition' => ['primera_visita', 'Sí'], 'help' => 'Si no tiene, escriba «Ninguna».']),
                    self::f('edad_menor', '¿El paciente es menor de edad?', 'radio', ['options' => ['No', 'Sí'], 'required' => 1]),
                    self::f('responsable', 'Nombre del padre, madre o encargado', 'text', ['required' => 1, 'condition' => ['edad_menor', 'Sí']]),
                ],
                'routing' => ['key' => 'necesidad', 'label' => '¿Qué necesita?', 'default' => 'valoracion-dental', 'options' => [
                    'Me duele una muela o tuve un golpe' => 'urgencia-dental',
                    'Limpieza' => 'limpieza-dental',
                    'Estoy en tratamiento de ortodoncia' => 'ortodoncia-control',
                    'Quiero mejorar el color de mis dientes' => 'blanqueamiento-dental',
                    'No sé; quiero una revisión' => 'valoracion-dental',
                ]],
            ],
            'psicologo' => [
                'name' => 'Psicología y terapia', 'icon' => 'user',
                'short' => 'Terapia individual, de pareja, juvenil y orientación en línea.',
                'terms' => ['cita' => 'sesión', 'plural' => 'sesiones', 'host' => 'terapeuta', 'client' => 'consultante'],
                'events' => [
                    self::ev('primera-sesion-terapia', 'Primera sesión de terapia', 'Un espacio para conocernos, entender qué te trae y acordar cómo trabajar juntos. Sin compromiso de continuar.', [60], 350, 'in_person', self::GOLD, ['buffer_after' => 15, 'cancel_hours' => 24]),
                    self::ev('sesion-terapia-individual', 'Sesión de terapia individual', 'Sesión de seguimiento de tu proceso terapéutico.', [50], 400, 'in_person', self::SAGE, ['buffer_after' => 10, 'cancel_hours' => 24]),
                    self::ev('terapia-en-linea', 'Terapia en línea', 'Sesión por videollamada desde donde estés. Busca un lugar tranquilo y con buena conexión.', [50], 375, 'video_auto', self::SLATE, ['buffer_after' => 10, 'cancel_hours' => 24]),
                    self::ev('terapia-de-pareja', 'Terapia de pareja', 'Sesión para trabajar la comunicación y los acuerdos de la relación. Deben asistir ambos.', [75], 600, 'in_person', self::ROSE, ['buffer_after' => 15, 'cancel_hours' => 48, 'guests' => 1]),
                    self::ev('orientacion-adolescentes', 'Orientación para adolescentes y familias', 'Acompañamiento a adolescentes y a sus padres o encargados.', [50], 400, 'in_person', self::PLUM, ['approval' => 1, 'buffer_after' => 10]),
                ],
                'fields' => [
                    self::f('motivo', '¿Qué te gustaría trabajar? (opcional)', 'textarea', ['help' => 'Solo lo que quieras compartir. Lo que escribas es confidencial.']),
                    self::f('primera_terapia', '¿Has asistido antes a terapia?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('terapia_previa', '¿Con quién y hace cuánto tiempo?', 'text', ['condition' => ['primera_terapia', 'Sí']]),
                    self::f('modalidad', '¿Prefieres sesiones presenciales o en línea?', 'select', ['options' => ['Presencial', 'En línea', 'Me da igual']]),
                    self::f('edad_consultante', '¿Quién asistirá a la sesión?', 'select', ['options' => ['Una persona adulta', 'Un menor de edad', 'Pareja'], 'required' => 1]),
                    self::f('responsable', 'Nombre del padre, madre o encargado', 'text', ['required' => 1, 'condition' => ['edad_consultante', 'Un menor de edad']]),
                ],
                'routing' => ['key' => 'para_quien', 'label' => '¿Para quién es la terapia?', 'default' => 'primera-sesion-terapia', 'options' => [
                    'Para mí' => 'primera-sesion-terapia',
                    'Para mi pareja y para mí' => 'terapia-de-pareja',
                    'Para mi hijo o hija adolescente' => 'orientacion-adolescentes',
                    'Vivo fuera de la ciudad o prefiero en línea' => 'terapia-en-linea',
                ]],
            ],
            'nutricionista' => [
                'name' => 'Nutrición', 'icon' => 'star',
                'short' => 'Evaluación nutricional, planes de alimentación y seguimiento.',
                'terms' => ['cita' => 'consulta', 'plural' => 'consultas', 'host' => 'nutricionista', 'client' => 'paciente'],
                'events' => [
                    self::ev('evaluacion-nutricional', 'Evaluación nutricional inicial', 'Historia alimentaria, medidas corporales, composición corporal y plan personalizado.', [60], 400, 'in_person', self::GOLD, ['buffer_after' => 10, 'cancel_hours' => 24]),
                    self::ev('control-nutricional', 'Control y ajuste de plan', 'Revisión de tu avance, medidas y ajustes al plan de alimentación.', [30], 250, 'in_person', self::SAGE, ['buffer_after' => 5, 'cancel_hours' => 12]),
                    self::ev('consulta-nutricional-en-linea', 'Consulta nutricional en línea', 'Seguimiento por videollamada. Ten a la mano tu peso reciente y tu registro de comidas.', [30, 45], 225, 'video_auto', self::SLATE, ['cancel_hours' => 12]),
                    self::ev('nutricion-deportiva', 'Nutrición deportiva', 'Plan enfocado en rendimiento, composición corporal y competencias.', [60], 450, 'in_person', self::TEAL, ['buffer_after' => 10]),
                ],
                'fields' => [
                    self::f('objetivo', '¿Cuál es su objetivo principal?', 'select', ['options' => ['Bajar de peso', 'Aumentar masa muscular', 'Mejorar mi alimentación', 'Una condición médica como diabetes o gastritis', 'Rendimiento deportivo', 'Otro'], 'required' => 1]),
                    self::f('condicion', '¿Qué condición médica tiene?', 'text', ['condition' => ['objetivo', 'Una condición médica como diabetes o gastritis']]),
                    self::f('primera_visita', '¿Es su primera consulta nutricional?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('peso_actual', 'Peso aproximado actual (libras)', 'number', ['condition' => ['primera_visita', 'Sí']]),
                    self::f('alergias', '¿Tiene alergias o intolerancias alimentarias?', 'text', ['help' => 'Si no tiene, escriba «Ninguna».']),
                ],
                'routing' => ['key' => 'situacion', 'label' => '¿Cuál es su situación?', 'default' => 'evaluacion-nutricional', 'options' => [
                    'Nunca he llevado un plan nutricional' => 'evaluacion-nutricional',
                    'Ya soy paciente y quiero un control' => 'control-nutricional',
                    'Entreno o compito' => 'nutricion-deportiva',
                    'Prefiero atenderme en línea' => 'consulta-nutricional-en-linea',
                ]],
            ],
            'fisioterapeuta' => [
                'name' => 'Fisioterapia y rehabilitación', 'icon' => 'zap',
                'short' => 'Evaluación, terapia física, rehabilitación deportiva y masaje terapéutico.',
                'terms' => ['cita' => 'sesión', 'plural' => 'sesiones', 'host' => 'fisioterapeuta', 'client' => 'paciente'],
                'events' => [
                    self::ev('evaluacion-fisioterapia', 'Evaluación fisioterapéutica', 'Valoración de movilidad, fuerza y dolor, con plan de tratamiento y número de sesiones sugerido.', [60], 300, 'in_person', self::GOLD, ['buffer_after' => 10, 'cancel_hours' => 12]),
                    self::ev('sesion-fisioterapia', 'Sesión de fisioterapia', 'Terapia manual, ejercicio terapéutico y agentes físicos según tu plan.', [45, 60], 250, 'in_person', self::SAGE, ['buffer_after' => 10, 'cancel_hours' => 12]),
                    self::ev('rehabilitacion-deportiva', 'Rehabilitación deportiva', 'Recuperación de lesiones y retorno seguro al deporte.', [60], 325, 'in_person', self::TEAL, ['buffer_after' => 10]),
                    self::ev('masaje-terapeutico', 'Masaje terapéutico', 'Masaje descontracturante para espalda, cuello y hombros.', [45, 60], 275, 'in_person', self::ROSE, ['buffer_after' => 15]),
                    self::ev('fisioterapia-a-domicilio', 'Fisioterapia a domicilio', 'Atendemos en tu casa dentro de la ciudad. Prepara un espacio libre para el ejercicio.', [60], 400, 'home', self::SLATE, ['travel' => 30, 'approval' => 1, 'cancel_hours' => 24]),
                ],
                'fields' => [
                    self::f('zona_dolor', '¿Dónde siente la molestia?', 'select', ['options' => ['Cuello', 'Espalda alta', 'Espalda baja', 'Hombro', 'Rodilla', 'Cadera', 'Tobillo o pie', 'Otra zona'], 'required' => 1]),
                    self::f('tiempo_molestia', '¿Desde cuándo?', 'select', ['options' => ['Menos de una semana', 'Entre una semana y un mes', 'Más de un mes']]),
                    self::f('indicacion', '¿Viene con indicación médica?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('diagnostico', 'Diagnóstico o indicación del médico', 'text', ['condition' => ['indicacion', 'Sí']]),
                    self::f('estudios', 'Adjunte estudios o referencia médica (opcional)', 'file', ['condition' => ['indicacion', 'Sí']]),
                    self::f('direccion_domicilio', 'Dirección donde le atenderemos', 'textarea', ['required' => 1, 'events' => ['fisioterapia-a-domicilio']]),
                ],
                'routing' => ['key' => 'tipo', 'label' => '¿Qué busca?', 'default' => 'evaluacion-fisioterapia', 'options' => [
                    'Es mi primera vez y me duele algo' => 'evaluacion-fisioterapia',
                    'Ya estoy en tratamiento' => 'sesion-fisioterapia',
                    'Me lesioné haciendo deporte' => 'rehabilitacion-deportiva',
                    'Solo quiero un masaje para relajar' => 'masaje-terapeutico',
                ]],
            ],
            'veterinario' => [
                'name' => 'Veterinaria', 'icon' => 'tag',
                'short' => 'Consultas, vacunación, cirugía, estética y urgencias para mascotas.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'veterinario(a)', 'client' => 'tutor'],
                'events' => [
                    self::ev('consulta-veterinaria', 'Consulta veterinaria', 'Examen clínico de tu mascota y recomendaciones de tratamiento.', [30], 200, 'in_person', self::GOLD, ['buffer_after' => 10, 'cancel_hours' => 6]),
                    self::ev('vacunacion-desparasitacion', 'Vacunación y desparasitación', 'Esquema de vacunas, desparasitación y control de peso. Trae el carné de tu mascota.', [20], 150, 'in_person', self::SAGE, ['buffer_after' => 5]),
                    self::ev('bano-y-estetica-canina', 'Baño y estética', 'Baño, corte de pelo y de uñas, limpieza de oídos.', [60, 90], 175, 'in_person', self::ROSE, ['buffer_after' => 15, 'cancel_hours' => 12]),
                    self::ev('cirugia-veterinaria', 'Valoración quirúrgica', 'Evaluación previa para esterilizaciones y cirugías. Requiere ayuno según indicación.', [30], 250, 'in_person', self::PLUM, ['approval' => 1, 'cancel_hours' => 24]),
                    self::ev('urgencia-veterinaria', 'Urgencia veterinaria', 'Atención prioritaria para intoxicaciones, golpes, vómitos persistentes o dificultad para respirar.', [30], 350, 'in_person', self::SLATE, ['notice' => 30, 'cancel_hours' => 1]),
                ],
                'fields' => [
                    self::f('mascota_nombre', 'Nombre de la mascota', 'text', ['required' => 1]),
                    self::f('especie', 'Especie', 'select', ['options' => ['Perro', 'Gato', 'Ave', 'Conejo', 'Otra'], 'required' => 1]),
                    self::f('raza_edad', 'Raza y edad aproximada', 'text'),
                    self::f('primera_visita', '¿Es su primera visita a la clínica?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('motivo', '¿Qué le ocurre o qué necesita?', 'textarea', ['required' => 1]),
                    self::f('vacunas_al_dia', '¿Tiene sus vacunas al día?', 'radio', ['options' => ['Sí', 'No', 'No lo sé'], 'condition' => ['primera_visita', 'Sí']]),
                ],
                'routing' => ['key' => 'situacion', 'label' => '¿Qué le pasa a su mascota?', 'default' => 'consulta-veterinaria', 'options' => [
                    'Es una emergencia' => 'urgencia-veterinaria',
                    'Está enferma o decaída' => 'consulta-veterinaria',
                    'Toca vacuna o desparasitación' => 'vacunacion-desparasitacion',
                    'Quiero baño o corte' => 'bano-y-estetica-canina',
                    'Quiero esterilizarla' => 'cirugia-veterinaria',
                ]],
            ],
            'abogado' => [
                'name' => 'Abogacía y bufete', 'icon' => 'file',
                'short' => 'Asesoría legal, revisión de contratos y atención a empresas y familias.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'abogado(a)', 'client' => 'cliente'],
                'events' => [
                    self::ev('consulta-legal-inicial', 'Consulta legal inicial', 'Análisis de tu caso y ruta de acción recomendada. Trae los documentos que tengas a la mano.', [45], 400, 'in_person', self::GOLD, ['buffer_after' => 15, 'cancel_hours' => 24]),
                    self::ev('asesoria-legal-en-linea', 'Asesoría legal por videollamada', 'Consulta a distancia con revisión de documentos compartidos por correo.', [30, 60], 350, 'video_auto', self::SLATE, ['cancel_hours' => 12]),
                    self::ev('revision-de-contratos', 'Revisión de contratos', 'Revisión de contratos de arrendamiento, servicios, laborales o sociedades, con observaciones por escrito.', [60], 600, 'in_person', self::BRASS, ['approval' => 1, 'deposit' => ['percent', 50], 'cancel_hours' => 48]),
                    self::ev('asesoria-empresarial', 'Asesoría para empresas', 'Constitución de sociedades, patentes de comercio, asuntos laborales y cumplimiento.', [60], 800, 'in_person', self::TEAL, ['approval' => 1, 'cancel_hours' => 48]),
                    self::ev('derecho-de-familia', 'Derecho de familia', 'Pensión alimenticia, divorcio, custodia y sucesiones. Atención reservada.', [60], 450, 'in_person', self::ROSE, ['buffer_after' => 15, 'cancel_hours' => 24]),
                ],
                'fields' => [
                    self::f('area_legal', '¿Sobre qué área es su consulta?', 'select', ['options' => ['Civil', 'Mercantil', 'Laboral', 'Familia', 'Penal', 'Inmobiliario', 'Otra'], 'required' => 1]),
                    self::f('resumen_caso', 'Resumen breve de su caso', 'textarea', ['required' => 1, 'help' => 'No incluya datos sensibles que no sean necesarios.']),
                    self::f('hay_proceso', '¿Ya existe un proceso judicial?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('numero_expediente', 'Número de expediente y juzgado', 'text', ['condition' => ['hay_proceso', 'Sí']]),
                    self::f('documentos', 'Adjunte documentos (opcional)', 'file', ['help' => 'Contratos, resoluciones o cualquier documento relacionado.']),
                    self::f('empresa', 'Nombre de la empresa', 'text', ['events' => ['asesoria-empresarial', 'revision-de-contratos']]),
                ],
                'routing' => ['key' => 'tipo_asunto', 'label' => '¿Qué necesita?', 'default' => 'consulta-legal-inicial', 'options' => [
                    'Tengo un problema legal y no sé por dónde empezar' => 'consulta-legal-inicial',
                    'Necesito revisar o firmar un contrato' => 'revision-de-contratos',
                    'Asunto de mi empresa' => 'asesoria-empresarial',
                    'Asunto de familia' => 'derecho-de-familia',
                    'Estoy fuera de la ciudad' => 'asesoria-legal-en-linea',
                ]],
            ],
            'notario' => [
                'name' => 'Notaría', 'icon' => 'receipt',
                'short' => 'Escrituras, actas, auténticas, poderes y trámites notariales.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'notario(a)', 'client' => 'cliente'],
                'events' => [
                    self::ev('asesoria-notarial', 'Asesoría notarial', 'Orientación sobre el trámite que necesitas, documentos requeridos y costos.', [30], 200, 'in_person', self::GOLD, ['cancel_hours' => 12, 'buffer_after' => 10]),
                    self::ev('firma-de-escritura-publica', 'Firma de escritura pública', 'Lectura y firma de escrituras de compraventa, hipoteca, donación o sociedad. Todos los otorgantes deben presentar su DPI.', [60], 0, 'in_person', self::BRASS, ['approval' => 1, 'cancel_hours' => 48, 'buffer_before' => 15, 'buffer_after' => 15, 'guests' => 4]),
                    self::ev('autentica-y-poder', 'Auténtica de firmas y poderes', 'Legalización de firmas, mandatos y poderes. Trae tu DPI vigente y el documento a firmar sin firmar.', [20, 30], 150, 'in_person', self::SAND, ['buffer_after' => 5, 'cancel_hours' => 6]),
                    self::ev('acta-notarial', 'Acta notarial', 'Actas de declaración jurada, de presencia o de matrimonio. Se agenda según el tipo de acta.', [45], 350, 'in_person', self::TEAL, ['approval' => 1, 'cancel_hours' => 24]),
                ],
                'fields' => [
                    self::f('tramite', '¿Qué trámite necesita?', 'select', ['options' => ['Compraventa de inmueble', 'Compraventa de vehículo', 'Poder o mandato', 'Autenticar firma', 'Sociedad o empresa', 'Declaración jurada', 'Matrimonio o unión de hecho', 'Otro'], 'required' => 1]),
                    self::f('otros_otorgantes', 'Cantidad de personas que firmarán', 'number', ['required' => 1, 'help' => 'Incluya a todas las personas que deben comparecer.']),
                    self::f('tiene_documentos', '¿Ya tiene los documentos listos?', 'radio', ['options' => ['Sí', 'No', 'No sé cuáles necesito'], 'required' => 1]),
                    self::f('documentos', 'Adjunte copia de los documentos (opcional)', 'file', ['condition' => ['tiene_documentos', 'Sí']]),
                    self::f('finca', 'Número de finca, folio y libro (si es inmueble)', 'text', ['condition' => ['tramite', 'Compraventa de inmueble']]),
                ],
                'routing' => ['key' => 'tramite', 'label' => '¿Qué necesita hacer?', 'default' => 'asesoria-notarial', 'options' => [
                    'Comprar o vender un inmueble' => 'firma-de-escritura-publica',
                    'Un poder o autenticar mi firma' => 'autentica-y-poder',
                    'Una declaración jurada o acta' => 'acta-notarial',
                    'No sé qué trámite necesito' => 'asesoria-notarial',
                ]],
            ],
            'contador' => [
                'name' => 'Contaduría y auditoría', 'icon' => 'chart',
                'short' => 'Asesoría fiscal, SAT, contabilidad y planificación para personas y empresas.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'contador(a)', 'client' => 'cliente'],
                'events' => [
                    self::ev('asesoria-contable-inicial', 'Asesoría contable inicial', 'Revisamos tu situación fiscal y contable y te proponemos cómo ordenarla.', [45], 250, 'in_person', self::GOLD, ['cancel_hours' => 12, 'buffer_after' => 10]),
                    self::ev('declaracion-de-impuestos', 'Declaraciones de impuestos ante la SAT', 'Preparación de declaraciones de IVA, ISR y retenciones. Trae tus facturas del periodo.', [30], 300, 'in_person', self::BRASS, ['cancel_hours' => 12]),
                    self::ev('apertura-de-negocio-sat', 'Apertura de negocio ante la SAT', 'Inscripción en el RTU, elección de régimen y habilitación de facturas electrónicas.', [60], 500, 'in_person', self::TEAL, ['approval' => 1, 'cancel_hours' => 24]),
                    self::ev('planificacion-fiscal', 'Planificación fiscal para empresas', 'Estrategia para optimizar tu carga tributaria dentro de la ley y cierre contable.', [60], 700, 'video_auto', self::SLATE, ['approval' => 1, 'deposit' => ['percent', 50], 'cancel_hours' => 48]),
                ],
                'fields' => [
                    self::f('tipo_contribuyente', '¿Es persona individual o empresa?', 'radio', ['options' => ['Persona individual', 'Empresa'], 'required' => 1]),
                    self::f('regimen', 'Régimen ante la SAT', 'select', ['options' => ['Pequeño contribuyente', 'Opcional simplificado sobre ingresos', 'Utilidades de actividades lucrativas', 'No lo sé'], 'condition' => ['tipo_contribuyente', 'Persona individual']]),
                    self::f('razon_social', 'Razón social y NIT de la empresa', 'text', ['required' => 1, 'condition' => ['tipo_contribuyente', 'Empresa']]),
                    self::f('necesidad', '¿Qué necesita resolver?', 'textarea', ['required' => 1]),
                    self::f('documentos', 'Adjunte su último estado de cuenta o declaración (opcional)', 'file'),
                ],
                'routing' => ['key' => 'necesidad', 'label' => '¿En qué podemos ayudarte?', 'default' => 'asesoria-contable-inicial', 'options' => [
                    'Voy a abrir un negocio' => 'apertura-de-negocio-sat',
                    'Declarar mis impuestos' => 'declaracion-de-impuestos',
                    'Quiero pagar menos impuestos legalmente' => 'planificacion-fiscal',
                    'Tengo una carta de la SAT' => 'asesoria-contable-inicial',
                ]],
            ],
            'arquitecto_ingeniero' => [
                'name' => 'Arquitectura e ingeniería', 'icon' => 'building',
                'short' => 'Visitas técnicas, anteproyectos, planos y supervisión de obra.',
                'terms' => ['cita' => 'reunión', 'plural' => 'reuniones', 'host' => 'profesional', 'client' => 'cliente'],
                'events' => [
                    self::ev('reunion-de-proyecto', 'Reunión inicial de proyecto', 'Conversamos sobre tu terreno, presupuesto y necesidades para definir el alcance.', [45], 0, 'in_person', self::GOLD, ['cancel_hours' => 24, 'buffer_after' => 15]),
                    self::ev('visita-tecnica-terreno', 'Visita técnica al terreno u obra', 'Inspección en sitio con registro fotográfico y observaciones. Dentro de la ciudad.', [90], 600, 'in_person', self::BRASS, ['travel' => 30, 'approval' => 1, 'cancel_hours' => 24, 'buffer_after' => 15]),
                    self::ev('revision-de-planos', 'Revisión de planos y presupuesto', 'Revisión técnica de planos, renders y cotizaciones que ya tengas.', [60], 350, 'video_auto', self::SLATE, ['cancel_hours' => 12]),
                    self::ev('supervision-de-obra', 'Supervisión de obra', 'Visita de supervisión con informe de avance y control de calidad.', [120], 900, 'in_person', self::TEAL, ['travel' => 30, 'approval' => 1, 'cancel_hours' => 48]),
                ],
                'fields' => [
                    self::f('tipo_proyecto', '¿Qué tipo de proyecto tiene?', 'select', ['options' => ['Casa nueva', 'Remodelación o ampliación', 'Local comercial', 'Edificio', 'Proyecto industrial', 'Otro'], 'required' => 1]),
                    self::f('ubicacion', 'Ubicación del proyecto', 'text', ['required' => 1, 'help' => 'Zona, municipio o colonia.']),
                    self::f('tiene_terreno', '¿Ya tiene terreno?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('area_terreno', 'Tamaño aproximado del terreno (m²)', 'number', ['condition' => ['tiene_terreno', 'Sí']]),
                    self::f('presupuesto', 'Presupuesto estimado (Q)', 'select', ['options' => ['Menos de Q250 mil', 'De Q250 mil a Q750 mil', 'De Q750 mil a Q2 millones', 'Más de Q2 millones', 'Aún no lo he definido']]),
                    self::f('referencias', 'Adjunte fotos, planos o referencias (opcional)', 'file'),
                ],
                'routing' => ['key' => 'etapa', 'label' => '¿En qué etapa está su proyecto?', 'default' => 'reunion-de-proyecto', 'options' => [
                    'Apenas tengo la idea' => 'reunion-de-proyecto',
                    'Ya tengo terreno y quiero que lo visiten' => 'visita-tecnica-terreno',
                    'Ya tengo planos y quiero una segunda opinión' => 'revision-de-planos',
                    'La obra está en marcha' => 'supervision-de-obra',
                ]],
            ],
            'consultor_coach' => [
                'name' => 'Consultoría y coaching', 'icon' => 'route',
                'short' => 'Sesiones de diagnóstico, coaching individual y talleres de equipo.',
                'terms' => ['cita' => 'sesión', 'plural' => 'sesiones', 'host' => 'consultor(a)', 'client' => 'cliente'],
                'events' => [
                    self::ev('sesion-de-diagnostico', 'Sesión de diagnóstico', 'Conversación sin costo para entender tu reto y ver si podemos trabajar juntos.', [30], 0, 'video_auto', self::GOLD, ['cancel_hours' => 12, 'buffer_after' => 10]),
                    self::ev('sesion-de-coaching', 'Sesión de coaching individual', 'Trabajo enfocado en tus metas, decisiones y hábitos con acuerdos de seguimiento.', [60], 500, 'video_auto', self::SAGE, ['buffer_after' => 15, 'cancel_hours' => 24]),
                    self::ev('consultoria-estrategica', 'Consultoría estratégica', 'Sesión de trabajo para revisar estrategia, procesos o resultados de tu negocio.', [90], 900, 'video_auto', self::BRASS, ['approval' => 1, 'deposit' => ['percent', 50], 'cancel_hours' => 48]),
                    self::ev('taller-para-equipos', 'Taller para equipos', 'Taller práctico para grupos pequeños, presencial o en línea.', [120], 250, 'in_person', self::PLUM, ['kind' => 'group', 'capacity' => 12, 'cancel_hours' => 48, 'approval' => 1]),
                ],
                'fields' => [
                    self::f('reto', '¿Cuál es su principal reto hoy?', 'textarea', ['required' => 1]),
                    self::f('empresa', 'Empresa o negocio', 'text'),
                    self::f('rol', '¿Cuál es su rol?', 'select', ['options' => ['Dueño(a) o gerente', 'Líder de equipo', 'Profesional independiente', 'Persona en transición profesional', 'Otro']]),
                    self::f('tamano_equipo', 'Tamaño del equipo', 'select', ['options' => ['Solo yo', '2 a 10 personas', '11 a 50 personas', 'Más de 50'], 'condition' => ['rol', 'Dueño(a) o gerente']]),
                    self::f('participantes', 'Número de participantes del taller', 'number', ['required' => 1, 'events' => ['taller-para-equipos']]),
                ],
                'routing' => ['key' => 'busca', 'label' => '¿Qué está buscando?', 'default' => 'sesion-de-diagnostico', 'options' => [
                    'Conocerlos antes de decidir' => 'sesion-de-diagnostico',
                    'Coaching para mí' => 'sesion-de-coaching',
                    'Mejorar mi negocio o empresa' => 'consultoria-estrategica',
                    'Capacitar a mi equipo' => 'taller-para-equipos',
                ]],
            ],
            'estetica_spa' => [
                'name' => 'Estética, belleza y spa', 'icon' => 'gift',
                'short' => 'Faciales, masajes, uñas, depilación y paquetes de bienestar.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'especialista', 'client' => 'clienta'],
                'events' => [
                    self::ev('limpieza-facial-profunda', 'Limpieza facial profunda', 'Limpieza, exfoliación, extracción y mascarilla según tu tipo de piel.', [60], 350, 'in_person', self::GOLD, ['buffer_after' => 15, 'cancel_hours' => 12]),
                    self::ev('masaje-relajante', 'Masaje relajante', 'Masaje de cuerpo completo con aceites aromáticos para soltar tensión.', [60, 90], 400, 'in_person', self::SAGE, ['buffer_after' => 15, 'cancel_hours' => 12]),
                    self::ev('manicure-y-pedicure', 'Manicure y pedicure', 'Cuidado de manos y pies con esmaltado tradicional o semipermanente.', [60, 90], 225, 'in_person', self::ROSE, ['buffer_after' => 10, 'cancel_hours' => 6]),
                    self::ev('depilacion-laser', 'Depilación láser', 'Sesión de depilación láser por zona. Requiere valoración previa.', [30, 45], 500, 'in_person', self::PLUM, ['approval' => 1, 'deposit' => ['fixed', 100], 'cancel_hours' => 24]),
                    self::ev('dia-de-spa', 'Día de spa para dos', 'Masaje, facial y área de relajación para ti y tu acompañante.', [180], 1500, 'in_person', self::TEAL, ['deposit' => ['percent', 50], 'cancel_hours' => 48, 'guests' => 1, 'approval' => 1]),
                ],
                'fields' => [
                    self::f('primera_visita', '¿Es su primera visita?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('tipo_piel', '¿Cómo describiría su piel?', 'select', ['options' => ['Normal', 'Seca', 'Grasa', 'Mixta', 'Sensible', 'No lo sé'], 'events' => ['limpieza-facial-profunda', 'depilacion-laser']]),
                    self::f('alergias', '¿Tiene alergias o condiciones de piel?', 'text', ['help' => 'Si no tiene, escriba «Ninguna».', 'condition' => ['primera_visita', 'Sí']]),
                    self::f('embarazo', '¿Está embarazada o en lactancia?', 'radio', ['options' => ['No', 'Sí'], 'events' => ['masaje-relajante', 'depilacion-laser', 'limpieza-facial-profunda']]),
                    self::f('presion_masaje', '¿Qué presión prefiere en el masaje?', 'select', ['options' => ['Suave', 'Media', 'Fuerte'], 'events' => ['masaje-relajante', 'dia-de-spa']]),
                ],
                'routing' => ['key' => 'servicio', 'label' => '¿Qué le gustaría hacerse?', 'default' => 'limpieza-facial-profunda', 'options' => [
                    'Cuidar mi piel' => 'limpieza-facial-profunda',
                    'Relajarme' => 'masaje-relajante',
                    'Uñas' => 'manicure-y-pedicure',
                    'Depilación definitiva' => 'depilacion-laser',
                    'Un regalo o plan en pareja' => 'dia-de-spa',
                ]],
            ],
            'academia_tutor' => [
                'name' => 'Academia y tutorías', 'icon' => 'layers',
                'short' => 'Clases particulares, grupos, reforzamiento y orientación vocacional.',
                'terms' => ['cita' => 'clase', 'plural' => 'clases', 'host' => 'profesor(a)', 'client' => 'estudiante'],
                'events' => [
                    self::ev('clase-de-prueba', 'Clase de prueba', 'Una primera clase para conocer el método y definir el nivel del estudiante.', [45], 0, 'in_person', self::GOLD, ['cancel_hours' => 12, 'buffer_after' => 10]),
                    self::ev('tutoria-individual', 'Tutoría individual', 'Clase personalizada de refuerzo en la materia que necesites.', [60], 150, 'in_person', self::SAGE, ['cancel_hours' => 12, 'buffer_after' => 10]),
                    self::ev('tutoria-en-linea', 'Tutoría en línea', 'Clase por videollamada con pizarra compartida.', [60], 125, 'video_auto', self::SLATE, ['cancel_hours' => 6]),
                    self::ev('clase-grupal', 'Clase grupal', 'Grupo reducido por nivel. Cupo limitado.', [90], 75, 'in_person', self::TEAL, ['kind' => 'group', 'capacity' => 8, 'cancel_hours' => 24, 'buffer_after' => 10]),
                    self::ev('orientacion-vocacional', 'Orientación vocacional', 'Sesión para elegir carrera y universidad, con pruebas de intereses.', [60], 300, 'in_person', self::PLUM, ['approval' => 1, 'cancel_hours' => 24]),
                ],
                'fields' => [
                    self::f('estudiante', 'Nombre del estudiante', 'text', ['required' => 1]),
                    self::f('grado', 'Grado o nivel', 'select', ['options' => ['Preprimaria', 'Primaria', 'Básicos', 'Diversificado', 'Universidad', 'Adulto'], 'required' => 1]),
                    self::f('materia', 'Materia o tema', 'text', ['required' => 1, 'help' => 'Por ejemplo: matemática, inglés, física, redacción.']),
                    self::f('es_menor', '¿El estudiante es menor de edad?', 'radio', ['options' => ['Sí', 'No'], 'required' => 1]),
                    self::f('encargado', 'Nombre del padre, madre o encargado', 'text', ['required' => 1, 'condition' => ['es_menor', 'Sí']]),
                    self::f('meta', '¿Qué quiere lograr?', 'textarea', ['help' => 'Una nota, un examen próximo, mejorar el nivel…']),
                ],
                'routing' => ['key' => 'modalidad', 'label' => '¿Cómo prefiere tomar las clases?', 'default' => 'clase-de-prueba', 'options' => [
                    'Quiero probar primero' => 'clase-de-prueba',
                    'Individual y presencial' => 'tutoria-individual',
                    'Individual en línea' => 'tutoria-en-linea',
                    'En grupo' => 'clase-grupal',
                    'Elegir carrera' => 'orientacion-vocacional',
                ]],
            ],
            'ventas_reuniones' => [
                'name' => 'Ventas y reuniones comerciales', 'icon' => 'users',
                'short' => 'Demostraciones, reuniones de descubrimiento y cierres con clientes.',
                'terms' => ['cita' => 'reunión', 'plural' => 'reuniones', 'host' => 'ejecutivo(a)', 'client' => 'cliente'],
                'events' => [
                    self::ev('reunion-de-descubrimiento', 'Reunión de descubrimiento', 'Conversación corta para entender qué necesitas y si podemos ayudarte.', [20, 30], 0, 'video_auto', self::GOLD, ['cancel_hours' => 4, 'buffer_after' => 5, 'notice' => 60]),
                    self::ev('demostracion-de-producto', 'Demostración personalizada', 'Te mostramos la solución aplicada a tu caso, con tiempo para preguntas.', [45], 0, 'video_auto', self::SLATE, ['cancel_hours' => 4, 'buffer_after' => 10]),
                    self::ev('reunion-de-propuesta', 'Presentación de propuesta', 'Revisamos la propuesta comercial, alcances y tiempos de implementación.', [60], 0, 'in_person', self::BRASS, ['cancel_hours' => 12, 'buffer_after' => 15]),
                    self::ev('visita-a-cliente', 'Visita a tu empresa', 'Visitamos tus instalaciones dentro de la ciudad para ver tu operación.', [60], 0, 'in_person', self::TEAL, ['travel' => 30, 'approval' => 1, 'cancel_hours' => 24]),
                ],
                'fields' => [
                    self::f('empresa', 'Empresa', 'text', ['required' => 1]),
                    self::f('cargo', 'Su cargo', 'text'),
                    self::f('tamano', 'Tamaño de la empresa', 'select', ['options' => ['1 a 10 personas', '11 a 50 personas', '51 a 200 personas', 'Más de 200 personas'], 'required' => 1]),
                    self::f('interes', '¿Qué le interesa resolver?', 'textarea', ['required' => 1]),
                    self::f('decisor', '¿Usted toma la decisión de compra?', 'radio', ['options' => ['Sí', 'La decide otra persona', 'Lo decidimos en equipo']]),
                    self::f('nombre_decisor', 'Nombre y cargo de quien decide', 'text', ['condition' => ['decisor', 'La decide otra persona'], 'help' => 'Si puede acompañarnos en la reunión, avísenos.']),
                ],
                'routing' => ['key' => 'etapa', 'label' => '¿En qué momento está?', 'default' => 'reunion-de-descubrimiento', 'options' => [
                    'Estoy investigando' => 'reunion-de-descubrimiento',
                    'Quiero ver el producto funcionando' => 'demostracion-de-producto',
                    'Ya recibí una cotización' => 'reunion-de-propuesta',
                    'Prefiero que me visiten' => 'visita-a-cliente',
                ]],
            ],
            'otro' => [
                'name' => 'Otro tipo de negocio', 'icon' => 'calendar',
                'short' => 'Punto de partida neutro: una cita general, una videollamada y una reunión rápida.',
                'terms' => ['cita' => 'cita', 'plural' => 'citas', 'host' => 'profesional', 'client' => 'cliente'],
                'events' => [
                    self::ev('cita-general', 'Cita general', 'Reserva un espacio para que te atendamos con calma.', [30, 60], 0, 'in_person', self::GOLD, ['buffer_after' => 10]),
                    self::ev('videollamada', 'Videollamada', 'Conversemos a distancia. Te enviamos el enlace por correo.', [30], 0, 'video_auto', self::SLATE),
                    self::ev('llamada-rapida', 'Llamada rápida', 'Una llamada breve para resolver una duda puntual.', [15], 0, 'phone', self::SAGE, ['cancel_hours' => 2]),
                ],
                'fields' => [
                    self::f('motivo', '¿En qué podemos ayudarle?', 'textarea', ['help' => 'Cuéntenos brevemente para llegar preparados.']),
                    self::f('primera_visita', '¿Es la primera vez que nos visita?', 'radio', ['options' => ['Sí', 'No']]),
                    self::f('como_nos_conocio', '¿Cómo nos conoció?', 'select', ['options' => ['Recomendación', 'Redes sociales', 'Búsqueda en internet', 'Otro'], 'condition' => ['primera_visita', 'Sí']]),
                ],
                'routing' => null,
            ],
        ];
    }
}
