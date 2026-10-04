<?php
/**
 * Presets de profesión para Aurea (terminología, servicios y formularios).
 */

$s = function ($cat, $name, $desc, $duration, $price, $modality = 'presencial', $depType = 'none', $depVal = 0, $buffer = 0, $capacity = 1) {
    return [
        'cat' => $cat, 'name' => $name, 'desc' => $desc, 'duration' => $duration,
        'price' => (float) $price, 'modality' => $modality, 'deposit_type' => $depType,
        'deposit_value' => $depVal, 'buffer_after' => $buffer, 'capacity' => $capacity,
    ];
};
$f = function ($label, $type, $required, $options = '') {
    return ['label' => $label, 'type' => $type, 'required' => $required, 'options' => $options];
};
$t = function ($c, $cs, $a, $as, $p, $ps) {
    return ['client' => $c, 'clients' => $cs, 'appt' => $a, 'appts' => $as, 'professional' => $p, 'professionals' => $ps];
};

return [

'medico_general' => [
    'label' => 'Médico general',
    'terms' => $t('Paciente', 'Pacientes', 'Consulta', 'Consultas', 'Médico', 'Médicos'),
    'headline' => 'Su salud, en manos de un médico de confianza',
    'subtitle' => 'Agende su consulta en línea en pocos pasos. Atención cercana, diagnóstico claro y seguimiento para usted y su familia.',
    'categories' => ['Consultas', 'Prevención y control', 'Atención en línea'],
    'services' => [
        $s(0, 'Consulta médica general', 'Evaluación completa de síntomas, diagnóstico y tratamiento indicado.', 30, 250),
        $s(0, 'Consulta de seguimiento', 'Control de su evolución y ajuste del tratamiento si es necesario.', 20, 175),
        $s(1, 'Chequeo médico preventivo', 'Revisión general con historial, examen físico y orden de laboratorios.', 45, 400),
        $s(1, 'Control de presión y diabetes', 'Seguimiento de enfermedades crónicas con revisión de resultados.', 30, 225),
        $s(2, 'Teleconsulta', 'Consulta médica por videollamada desde la comodidad de su hogar.', 25, 200, 'virtual'),
        $s(0, 'Certificado médico', 'Evaluación y emisión de certificado de salud para trabajo o estudios.', 20, 150),
    ],
    'form' => [
        $f('Motivo de la consulta', 'textarea', 1),
        $f('¿Padece alguna enfermedad crónica?', 'select', 1, 'No|Diabetes|Hipertensión|Asma|Otra'),
        $f('¿Es alérgico a algún medicamento? ¿Cuál?', 'text', 0),
        $f('Medicamentos que toma actualmente', 'textarea', 0),
        $f('Acepto el tratamiento de mis datos de salud para fines de atención médica', 'consent', 1),
    ],
],

'especialista' => [
    'label' => 'Médico especialista',
    'terms' => $t('Paciente', 'Pacientes', 'Consulta', 'Consultas', 'Especialista', 'Especialistas'),
    'headline' => 'Atención especializada, con la calidez que merece',
    'subtitle' => 'Reserve su consulta con el especialista y reciba un diagnóstico preciso y un plan de tratamiento personalizado.',
    'categories' => ['Consultas', 'Estudios y procedimientos', 'Atención en línea'],
    'services' => [
        $s(0, 'Primera consulta especializada', 'Valoración inicial con revisión de historial y estudios previos.', 45, 450, 'presencial', 'fixed', 100),
        $s(0, 'Consulta de control', 'Seguimiento del tratamiento y revisión de resultados.', 30, 300),
        $s(1, 'Estudio diagnóstico en consultorio', 'Realización e interpretación de estudios básicos de la especialidad.', 40, 550, 'presencial', 'none', 0, 10),
        $s(1, 'Procedimiento menor ambulatorio', 'Procedimiento breve en consultorio con indicaciones posteriores.', 60, 900, 'presencial', 'percent', 30, 15),
        $s(2, 'Segunda opinión médica', 'Análisis de su caso y estudios por videollamada.', 40, 400, 'virtual'),
        $s(2, 'Teleconsulta de seguimiento', 'Control rápido por videollamada para pacientes ya atendidos.', 20, 225, 'virtual'),
    ],
    'form' => [
        $f('Especialidad o motivo de la consulta', 'textarea', 1),
        $f('¿Lo refirió otro médico?', 'select', 0, 'No|Sí'),
        $f('Diagnósticos o cirugías previas', 'textarea', 0),
        $f('Adjunte estudios o resultados recientes', 'file', 0),
        $f('Acepto el tratamiento de mis datos de salud', 'consent', 1),
    ],
],

'dentista' => [
    'label' => 'Dentista',
    'terms' => $t('Paciente', 'Pacientes', 'Cita', 'Citas', 'Odontólogo', 'Odontólogos'),
    'headline' => 'Una sonrisa sana empieza con una buena cita',
    'subtitle' => 'Agende su cita dental en línea. Tratamientos cómodos, tecnología moderna y atención sin dolor ni prisas.',
    'categories' => ['Prevención', 'Tratamientos', 'Estética dental'],
    'services' => [
        $s(0, 'Evaluación dental', 'Revisión completa, diagnóstico y plan de tratamiento.', 30, 150),
        $s(0, 'Limpieza dental profesional', 'Eliminación de sarro y placa con pulido y fluorización.', 45, 300, 'presencial', 'none', 0, 10),
        $s(1, 'Resina o empaste', 'Restauración de piezas con caries usando resina estética.', 45, 350, 'presencial', 'none', 0, 10),
        $s(1, 'Tratamiento de conductos', 'Endodoncia para conservar la pieza dental y eliminar el dolor.', 90, 1500, 'presencial', 'percent', 30, 15),
        $s(2, 'Blanqueamiento dental', 'Aclarado de hasta varios tonos en una sola sesión.', 60, 1200, 'presencial', 'percent', 30),
        $s(1, 'Consulta de urgencia dental', 'Atención prioritaria por dolor, fractura o inflamación.', 30, 250),
        $s(2, 'Valoración de ortodoncia', 'Análisis de su mordida y opciones de brackets o alineadores.', 30, 200),
    ],
    'form' => [
        $f('Motivo de la cita', 'textarea', 1),
        $f('¿Tiene alergias (anestesia, látex, penicilina)? ¿Cuáles?', 'text', 1),
        $f('¿Siente dolor actualmente?', 'select', 1, 'No|Leve|Moderado|Intenso'),
        $f('Fecha de su última visita al dentista', 'date', 0),
        $f('Acepto el tratamiento de mis datos de salud', 'consent', 1),
    ],
],

'psicologo' => [
    'label' => 'Psicólogo',
    'terms' => $t('Paciente', 'Pacientes', 'Sesión', 'Sesiones', 'Psicólogo', 'Psicólogos'),
    'headline' => 'Un espacio seguro para escucharle y acompañarle',
    'subtitle' => 'Reserve su sesión de forma confidencial, en consultorio o en línea. Dar el primer paso es lo más importante.',
    'categories' => ['Terapia individual', 'Pareja y familia', 'Evaluación'],
    'services' => [
        $s(0, 'Primera sesión de evaluación', 'Entrevista inicial para conocer su situación y definir objetivos.', 60, 400),
        $s(0, 'Sesión de terapia individual', 'Proceso terapéutico para ansiedad, depresión, duelo y estrés.', 50, 350, 'presencial', 'none', 0, 10),
        $s(0, 'Sesión de terapia en línea', 'Sesión por videollamada con la misma confidencialidad y calidad.', 50, 325, 'virtual', 'none', 0, 10),
        $s(1, 'Terapia de pareja', 'Acompañamiento para mejorar la comunicación y resolver conflictos.', 60, 500, 'presencial', 'none', 0, 10),
        $s(1, 'Orientación familiar', 'Sesión con la familia para fortalecer vínculos y acuerdos.', 60, 475),
        $s(2, 'Evaluación psicológica', 'Aplicación de pruebas con informe de resultados.', 90, 750, 'presencial', 'fixed', 200),
    ],
    'form' => [
        $f('Motivo de consulta', 'textarea', 1),
        $f('¿Es su primera vez en terapia?', 'select', 1, 'Sí|No'),
        $f('¿Ha recibido atención psiquiátrica o toma medicamentos?', 'select', 0, 'No|Sí'),
        $f('Preferencia de horario', 'select', 0, 'Mañana|Tarde|Noche'),
        $f('Acepto el manejo confidencial de mi información', 'consent', 1),
    ],
],

'nutricionista' => [
    'label' => 'Nutricionista',
    'terms' => $t('Paciente', 'Pacientes', 'Consulta', 'Consultas', 'Nutricionista', 'Nutricionistas'),
    'headline' => 'Coma mejor, sin dietas imposibles',
    'subtitle' => 'Planes de alimentación personalizados para su estilo de vida y sus metas. Reserve su consulta en línea.',
    'categories' => ['Consultas', 'Planes especializados', 'Seguimiento'],
    'services' => [
        $s(0, 'Primera consulta nutricional', 'Evaluación, composición corporal y plan de alimentación inicial.', 60, 350),
        $s(2, 'Consulta de seguimiento', 'Control de medidas, ajustes al plan y resolución de dudas.', 30, 200),
        $s(1, 'Nutrición deportiva', 'Plan enfocado en rendimiento, masa muscular y recuperación.', 60, 400),
        $s(1, 'Control de diabetes e hipertensión', 'Plan alimentario para el manejo de enfermedades crónicas.', 45, 375),
        $s(1, 'Nutrición infantil', 'Orientación para el crecimiento y hábitos saludables de niños.', 45, 325),
        $s(2, 'Consulta nutricional en línea', 'Asesoría y seguimiento por videollamada.', 40, 275, 'virtual'),
    ],
    'form' => [
        $f('Peso actual (kg)', 'number', 1),
        $f('Talla (cm)', 'number', 1),
        $f('Objetivo principal', 'select', 1, 'Bajar de peso|Aumentar masa muscular|Mejorar hábitos|Control de enfermedad|Otro'),
        $f('Alergias o intolerancias alimentarias', 'text', 0),
        $f('¿Qué come en un día normal?', 'textarea', 0),
    ],
],

'fisioterapeuta' => [
    'label' => 'Fisioterapeuta',
    'terms' => $t('Paciente', 'Pacientes', 'Sesión', 'Sesiones', 'Fisioterapeuta', 'Fisioterapeutas'),
    'headline' => 'Recupere el movimiento y viva sin dolor',
    'subtitle' => 'Rehabilitación y terapia física con un plan hecho a su medida. Agende su sesión en línea.',
    'categories' => ['Evaluación', 'Terapia física', 'Bienestar'],
    'services' => [
        $s(0, 'Evaluación fisioterapéutica', 'Valoración de su lesión o dolor y plan de tratamiento.', 45, 250),
        $s(1, 'Sesión de fisioterapia', 'Terapia manual, ejercicios y electroterapia según su caso.', 45, 225, 'presencial', 'none', 0, 10),
        $s(1, 'Rehabilitación postoperatoria', 'Recuperación guiada tras cirugía o fractura.', 60, 300, 'presencial', 'none', 0, 10),
        $s(2, 'Masaje terapéutico', 'Liberación de tensión muscular y contracturas.', 60, 275),
        $s(1, 'Terapia a domicilio', 'Sesión de fisioterapia en su hogar con equipo portátil.', 60, 350, 'domicilio'),
        $s(0, 'Asesoría de ejercicios en línea', 'Revisión de su rutina y ejercicios por videollamada.', 30, 175, 'virtual'),
    ],
    'form' => [
        $f('Zona de dolor o lesión', 'text', 1),
        $f('Describa su molestia y desde cuándo la tiene', 'textarea', 1),
        $f('¿Tiene diagnóstico o referencia médica?', 'select', 0, 'No|Sí'),
        $f('Adjunte estudios o recetas', 'file', 0),
        $f('Acepto el tratamiento de mis datos de salud', 'consent', 1),
    ],
],

'veterinario' => [
    'label' => 'Veterinario',
    'terms' => $t('Cliente', 'Clientes', 'Cita', 'Citas', 'Veterinario', 'Veterinarios'),
    'headline' => 'Cuidado profesional para quien más quiere',
    'subtitle' => 'Agende la cita de su mascota en línea. Vacunas, consultas y atención con cariño, en clínica o en su casa.',
    'categories' => ['Consultas', 'Prevención', 'Servicios adicionales'],
    'services' => [
        $s(0, 'Consulta veterinaria general', 'Revisión completa de su mascota y diagnóstico.', 30, 200),
        $s(1, 'Vacunación', 'Aplicación de vacunas con carné actualizado.', 20, 150),
        $s(1, 'Desparasitación y control', 'Tratamiento interno y externo contra parásitos.', 20, 125),
        $s(2, 'Baño y corte higiénico', 'Aseo completo con revisión de piel y oídos.', 60, 175, 'presencial', 'none', 0, 15),
        $s(0, 'Consulta veterinaria a domicilio', 'Atención en su hogar para mascotas que no pueden salir.', 45, 350, 'domicilio'),
        $s(0, 'Orientación veterinaria en línea', 'Consulta por videollamada para dudas de salud y conducta.', 20, 125, 'virtual'),
    ],
    'form' => [
        $f('Nombre de la mascota', 'text', 1),
        $f('Especie y raza', 'text', 1),
        $f('Edad de la mascota', 'text', 0),
        $f('Motivo de la cita o síntomas', 'textarea', 1),
        $f('¿Tiene sus vacunas al día?', 'select', 0, 'Sí|No|No lo sé'),
    ],
],

'abogado' => [
    'label' => 'Abogado',
    'terms' => $t('Cliente', 'Clientes', 'Asesoría', 'Asesorías', 'Abogado', 'Abogados'),
    'headline' => 'Defensa legal clara, seria y a su lado',
    'subtitle' => 'Reserve una asesoría jurídica confidencial. Le explicamos sus opciones con claridad y sin tecnicismos.',
    'categories' => ['Asesoría legal', 'Trámites y contratos', 'Litigio'],
    'services' => [
        $s(0, 'Primera asesoría legal', 'Análisis inicial de su caso y ruta de acción recomendada.', 45, 400, 'presencial', 'fixed', 150),
        $s(0, 'Asesoría legal en línea', 'Consulta jurídica por videollamada desde cualquier lugar.', 45, 350, 'virtual', 'fixed', 150),
        $s(1, 'Revisión de contratos', 'Análisis y recomendaciones sobre contratos y acuerdos.', 60, 600),
        $s(1, 'Asesoría en derecho de familia', 'Pensión alimenticia, custodia, divorcio y sucesiones.', 60, 500),
        $s(2, 'Asesoría laboral', 'Orientación sobre despidos, prestaciones y derechos laborales.', 45, 400),
        $s(2, 'Estrategia de litigio', 'Revisión de expediente y planeación de la defensa.', 90, 900, 'presencial', 'fixed', 300),
    ],
    'form' => [
        $f('Tipo de caso', 'select', 1, 'Civil|Penal|Laboral|Familia|Mercantil|Otro'),
        $f('Describa brevemente su situación', 'textarea', 1),
        $f('¿Existe un proceso judicial en curso?', 'select', 0, 'No|Sí'),
        $f('Adjunte documentos relacionados', 'file', 0),
        $f('Acepto el manejo confidencial de mi información', 'consent', 1),
    ],
],

'notario' => [
    'label' => 'Notario',
    'terms' => $t('Cliente', 'Clientes', 'Cita', 'Citas', 'Notario', 'Notarios'),
    'headline' => 'Certeza jurídica para sus actos más importantes',
    'subtitle' => 'Programe su cita notarial y llegue con todo listo. Escrituras, actas y legalizaciones con seguridad y rapidez.',
    'categories' => ['Escrituras', 'Actas y legalizaciones', 'Asesoría'],
    'services' => [
        $s(2, 'Asesoría notarial inicial', 'Orientación sobre el trámite, requisitos y costos aproximados.', 30, 300, 'presencial', 'fixed', 100),
        $s(0, 'Compraventa de inmueble', 'Escritura pública de compraventa con revisión de documentos.', 90, 2500, 'presencial', 'fixed', 500, 15),
        $s(0, 'Poder y mandato', 'Elaboración y firma de poder general o especial.', 45, 600),
        $s(1, 'Acta notarial', 'Acta de declaración, constancia o presencia de hechos.', 60, 450),
        $s(1, 'Legalización de firmas', 'Autenticación de firmas en documentos privados.', 20, 100),
        $s(2, 'Revisión de documentos en línea', 'Verificación previa de documentos por videollamada.', 30, 250, 'virtual'),
    ],
    'form' => [
        $f('Tipo de trámite', 'select', 1, 'Compraventa|Poder|Acta notarial|Legalización|Constitución de sociedad|Otro'),
        $f('Describa brevemente lo que necesita', 'textarea', 1),
        $f('Número de personas que comparecerán', 'number', 1),
        $f('Adjunte copia de DPI o documentos previos', 'file', 0),
    ],
],

'contador' => [
    'label' => 'Contador',
    'terms' => $t('Cliente', 'Clientes', 'Asesoría', 'Asesorías', 'Contador', 'Contadores'),
    'headline' => 'Sus números en orden, su negocio en crecimiento',
    'subtitle' => 'Asesoría contable y tributaria clara. Cumpla con la SAT sin dolores de cabeza y tome mejores decisiones.',
    'categories' => ['Asesoría', 'Impuestos', 'Contabilidad'],
    'services' => [
        $s(0, 'Asesoría contable inicial', 'Diagnóstico de su situación contable y fiscal.', 45, 300),
        $s(1, 'Declaración de ISR e IVA', 'Preparación y presentación de sus declaraciones ante la SAT.', 45, 400),
        $s(1, 'Inscripción en la SAT', 'Alta como contribuyente y elección del régimen adecuado.', 40, 350),
        $s(2, 'Cierre contable mensual', 'Revisión de libros y estados financieros del período.', 60, 600),
        $s(0, 'Planificación tributaria', 'Estrategia para optimizar su carga fiscal dentro de la ley.', 60, 500, 'virtual'),
        $s(0, 'Asesoría contable en línea', 'Resolución de dudas por videollamada.', 30, 250, 'virtual'),
    ],
    'form' => [
        $f('Tipo de contribuyente', 'select', 1, 'Persona individual|Empresa|Profesional liberal|Emprendedor'),
        $f('Régimen tributario actual', 'select', 0, 'Pequeño contribuyente|Opcional simplificado|Utilidades de actividades lucrativas|No lo sé'),
        $f('¿Qué necesita resolver?', 'textarea', 1),
        $f('Adjunte documentos o facturas de referencia', 'file', 0),
    ],
],

'arquitecto' => [
    'label' => 'Arquitecto',
    'terms' => $t('Cliente', 'Clientes', 'Asesoría', 'Asesorías', 'Arquitecto', 'Arquitectos'),
    'headline' => 'Diseñamos espacios que reflejan su forma de vivir',
    'subtitle' => 'Agende una asesoría y convierta su idea en un proyecto real, con diseño, planos y supervisión profesional.',
    'categories' => ['Asesoría', 'Diseño y planos', 'Obra'],
    'services' => [
        $s(0, 'Asesoría de proyecto', 'Conversación inicial sobre su terreno, necesidades y presupuesto.', 45, 350, 'presencial', 'fixed', 100),
        $s(0, 'Asesoría en línea', 'Revisión de su idea y orientación por videollamada.', 40, 300, 'virtual'),
        $s(1, 'Visita técnica al terreno', 'Inspección en sitio para evaluar medidas, suelo y entorno.', 90, 600, 'domicilio', 'fixed', 200),
        $s(1, 'Diseño de anteproyecto', 'Presentación de propuesta de distribución y volumetría.', 60, 800),
        $s(2, 'Revisión de planos', 'Análisis de planos existentes y recomendaciones.', 60, 500),
        $s(2, 'Supervisión de obra', 'Visita de revisión de avance y calidad de construcción.', 90, 700, 'domicilio'),
    ],
    'form' => [
        $f('Tipo de proyecto', 'select', 1, 'Casa nueva|Remodelación|Local comercial|Edificio|Otro'),
        $f('Ubicación del proyecto', 'text', 1),
        $f('Presupuesto aproximado (Q)', 'number', 0),
        $f('Cuéntenos su idea', 'textarea', 1),
        $f('Adjunte planos o fotografías', 'file', 0),
    ],
],

'consultor' => [
    'label' => 'Consultor',
    'terms' => $t('Cliente', 'Clientes', 'Asesoría', 'Asesorías', 'Consultor', 'Consultores'),
    'headline' => 'Estrategia clara para decisiones que importan',
    'subtitle' => 'Reserve una asesoría y obtenga un plan accionable para hacer crecer su negocio con orden y resultados.',
    'categories' => ['Estrategia', 'Operación', 'Talleres'],
    'services' => [
        $s(0, 'Primera asesoría estratégica', 'Diagnóstico inicial de su negocio y prioridades de acción.', 60, 500, 'virtual', 'fixed', 150),
        $s(0, 'Sesión de planeación', 'Definición de metas, indicadores y plan de trabajo trimestral.', 90, 800, 'presencial', 'fixed', 200),
        $s(1, 'Optimización de procesos', 'Análisis y mejora de la operación de su equipo.', 90, 750),
        $s(1, 'Asesoría de ventas y marketing', 'Estrategia comercial y canales para captar más clientes.', 60, 600, 'virtual'),
        $s(2, 'Taller para equipos', 'Capacitación práctica para su personal en un tema específico.', 120, 1500, 'presencial', 'percent', 30, 15, 1),
        $s(0, 'Sesión de seguimiento', 'Revisión de avances y ajuste del plan.', 45, 400, 'virtual'),
    ],
    'form' => [
        $f('Nombre de su empresa', 'text', 1),
        $f('Sector o industria', 'text', 1),
        $f('Tamaño del equipo', 'select', 0, '1 persona|2 a 10|11 a 50|Más de 50'),
        $f('¿Cuál es su principal reto actual?', 'textarea', 1),
    ],
],

'estetica' => [
    'label' => 'Estética y belleza',
    'terms' => $t('Cliente', 'Clientes', 'Cita', 'Citas', 'Especialista', 'Especialistas'),
    'headline' => 'Realce su belleza natural con cuidado experto',
    'subtitle' => 'Reserve su cita de belleza y bienestar en línea. Tratamientos personalizados en un ambiente relajante.',
    'categories' => ['Facial', 'Corporal', 'Manos y pies'],
    'services' => [
        $s(0, 'Limpieza facial profunda', 'Extracción, hidratación y mascarilla para una piel renovada.', 60, 275, 'presencial', 'none', 0, 10),
        $s(0, 'Tratamiento antiedad', 'Terapia facial con activos para reducir líneas de expresión.', 90, 650, 'presencial', 'percent', 30, 10),
        $s(1, 'Masaje relajante corporal', 'Masaje de cuerpo completo para liberar estrés y tensión.', 60, 350),
        $s(1, 'Tratamiento reductivo corporal', 'Sesión para modelar y reafirmar el cuerpo.', 90, 600, 'presencial', 'percent', 30, 15),
        $s(2, 'Manicure y pedicure spa', 'Cuidado completo de uñas con esmaltado semipermanente.', 90, 300, 'presencial', 'percent', 30),
        $s(0, 'Valoración estética en línea', 'Revisión de su piel y recomendación de tratamientos.', 20, 100, 'virtual'),
    ],
    'form' => [
        $f('¿Qué tratamiento le interesa?', 'text', 1),
        $f('¿Tiene alergias o piel sensible?', 'text', 1),
        $f('¿Está embarazada o en lactancia?', 'select', 1, 'No|Sí'),
        $f('Comentarios adicionales', 'textarea', 0),
        $f('Acepto las condiciones del servicio y política de cancelación', 'consent', 1),
    ],
],

'academia' => [
    'label' => 'Academia o escuela',
    'terms' => $t('Alumno', 'Alumnos', 'Clase', 'Clases', 'Instructor', 'Instructores'),
    'headline' => 'Aprenda a su ritmo con instructores que inspiran',
    'subtitle' => 'Reserve su lugar en clases individuales o grupales. Aprendizaje práctico con seguimiento real de su avance.',
    'categories' => ['Clases individuales', 'Clases grupales', 'Orientación'],
    'services' => [
        $s(2, 'Clase de prueba gratuita', 'Primera clase para conocer el método y definir su nivel.', 30, 0),
        $s(0, 'Clase individual', 'Clase personalizada con el instructor según sus objetivos.', 60, 150, 'presencial', 'none', 0, 10),
        $s(0, 'Clase individual en línea', 'Clase privada por videollamada con material compartido.', 60, 125, 'virtual', 'none', 0, 10),
        $s(1, 'Clase grupal', 'Clase en grupo reducido con práctica guiada y dinámica.', 90, 75, 'presencial', 'none', 0, 15, 8),
        $s(0, 'Tutoría de refuerzo', 'Apoyo puntual para exámenes, tareas o proyectos.', 60, 140),
        $s(2, 'Evaluación de nivel', 'Prueba diagnóstica con recomendación de plan de estudio.', 45, 100),
    ],
    'form' => [
        $f('Nombre del alumno', 'text', 1),
        $f('Edad del alumno', 'number', 1),
        $f('Nivel actual', 'select', 1, 'Principiante|Intermedio|Avanzado|No lo sé'),
        $f('¿Qué desea aprender o lograr?', 'textarea', 0),
        $f('Acepto las políticas de la academia', 'consent', 1),
    ],
],

'otro' => [
    'label' => 'Otro',
    'terms' => $t('Cliente', 'Clientes', 'Cita', 'Citas', 'Profesional', 'Profesionales'),
    'headline' => 'Reserve su cita de forma fácil y rápida',
    'subtitle' => 'Elija el servicio, el día y la hora que mejor le convengan. Le confirmaremos su cita al instante.',
    'categories' => ['Servicios', 'Atención en línea'],
    'services' => [
        $s(0, 'Primera cita', 'Reunión inicial para conocer sus necesidades y orientarle.', 45, 200),
        $s(0, 'Cita de seguimiento', 'Revisión de avances y definición de los próximos pasos.', 30, 150),
        $s(1, 'Sesión virtual', 'Atención por videollamada desde donde usted se encuentre.', 40, 175, 'virtual'),
        $s(0, 'Asesoría', 'Orientación profesional personalizada sobre su caso.', 60, 300, 'presencial', 'fixed', 75),
        $s(1, 'Visita a domicilio', 'Atención en el lugar que usted indique.', 60, 350, 'domicilio'),
    ],
    'form' => [
        $f('Motivo de la cita', 'textarea', 1),
        $f('¿Es su primera vez con nosotros?', 'select', 0, 'Sí|No'),
        $f('Comentarios adicionales', 'textarea', 0),
        $f('Acepto el tratamiento de mis datos personales', 'consent', 1),
    ],
],

];
