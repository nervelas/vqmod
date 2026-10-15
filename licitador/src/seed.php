<?php
/** Datos de ejemplo y contenido por defecto (todo editable luego desde el admin). */

function demo_catalog(): array {
    return [
    'construccion' => [
        ['Mejoramiento de camino rural hacia aldea {lugar}', 'Mejoramiento con balasto y cunetas de {n} km de camino rural, incluye alcantarillas de concreto.', [180000, 1900000]],
        ['Construcción de muro de contención en {lugar}', 'Muro de mampostería reforzada con drenaje, incluye excavación, armado de hierro y fundición de concreto.', [90000, 650000]],
        ['Remodelación de salón comunal de {lugar}', 'Remodelación de techo de lámina, repello, pintura, instalación eléctrica y piso cerámico.', [60000, 380000]],
        ['Introducción de drenaje sanitario en colonia {lugar}', 'Instalación de colector de PVC, pozos de visita y conexiones domiciliares.', [250000, 2400000]],
        ['Pavimentación de calle principal de {lugar}', 'Pavimentación con concreto hidráulico de {n} cuadras, incluye bordillos y banquetas.', [400000, 3800000]],
    ],
    'transporte' => [
        ['Suministro de combustible diésel para flotilla de {ent}', 'Suministro de diésel y gasolina regular para vehículos y maquinaria, por cupones o despacho directo.', [70000, 900000]],
        ['Servicio de transporte de personal de {ent}', 'Transporte terrestre en bus tipo microbús para traslado de personal y actividades institucionales.', [40000, 280000]],
        ['Compra de llantas y lubricantes para vehículos de {ent}', 'Suministro de llantas radiales, aceite de motor y filtros para pilotos y camiones.', [25000, 180000]],
        ['Mantenimiento de vehículos livianos de {ent}', 'Servicio de mantenimiento preventivo y correctivo de pickups y motocicletas institucionales.', [30000, 240000]],
    ],
    'medico' => [
        ['Adquisición de insumos médicos para el hospital de {lugar}', 'Jeringas, guantes de látex, mascarillas, gasas y material de curación para el área de emergencias.', [60000, 750000]],
        ['Compra de medicamentos esenciales para el centro de salud de {lugar}', 'Medicamentos del listado básico: acetaminofén, amoxicilina, suero oral y otros.', [80000, 1200000]],
        ['Suministro de reactivos de laboratorio clínico, {lugar}', 'Reactivos para hematología y química sanguínea, con calibradores y controles.', [50000, 520000]],
        ['Adquisición de equipo médico: tensiómetros y oxímetros, {lugar}', 'Equipo de diagnóstico básico para puestos de salud, con garantía de 1 año.', [25000, 160000]],
    ],
    'escolar' => [
        ['Suministro de útiles escolares para escuelas de {lugar}', 'Cuadernos, lápices, borradores, reglas y papel bond para programa de útiles escolares.', [40000, 700000]],
        ['Compra de mobiliario escolar: pupitres y pizarrones, {lugar}', 'Pupitres bipersonales y pizarrones de formica para centros educativos oficiales.', [70000, 560000]],
        ['Adquisición de libros de texto y material didáctico, {lugar}', 'Libros de lectura, mapas y material didáctico para primaria.', [30000, 330000]],
        ['Papelería y artículos de oficina para {ent}', 'Resmas, folders, tintas, sellos y artículos de librería para uso administrativo.', [15000, 140000]],
    ],
    'ferreteria' => [
        ['Suministro de tuberías PVC y accesorios para proyecto de agua potable, {lugar}', 'Tubería PVC de 1/2" a 4", codos, tees, pegamento y válvulas de compuerta.', [35000, 520000]],
        ['Compra de cemento, hierro y block para obras municipales de {lugar}', 'Cemento UGC, varillas corrugadas de 3/8" y 1/2", block de 15x20x40.', [60000, 880000]],
        ['Adquisición de herramientas y pintura para mantenimiento de {ent}', 'Palas, picos, carretillas, pintura látex y esmalte, brochas y rodillos.', [18000, 130000]],
        ['Suministro de material eléctrico y lámina galvanizada, {lugar}', 'Cable THHN, tableros, lámparas LED y lámina galvanizada calibre 28.', [30000, 300000]],
    ],
    'servicios' => [
        ['Servicio de seguridad y vigilancia para instalaciones de {ent}', 'Servicio de agentes de seguridad privada, turnos de 12 horas, por 12 meses.', [120000, 960000]],
        ['Consultoría para elaboración de estudio de factibilidad, {lugar}', 'Consultoría de diseño y estudio de factibilidad de proyecto de infraestructura social.', [60000, 480000]],
        ['Servicio de capacitación al personal de {ent}', 'Talleres de atención al ciudadano y gestión pública para 60 colaboradores.', [20000, 150000]],
        ['Servicio de impresión y publicidad institucional de {ent}', 'Impresión de afiches, manuales y banners para campaña informativa.', [15000, 120000]],
    ],
    'tecnologia' => [
        ['Adquisición de computadoras de escritorio y laptops para {ent}', 'Equipo de cómputo con procesador de 12ª generación, 16 GB de RAM y garantía de 3 años.', [90000, 780000]],
        ['Suministro de tóner e impresoras multifuncionales, {ent}', 'Tóner original, impresoras láser y servicio de instalación.', [25000, 210000]],
        ['Licenciamiento de software de oficina y antivirus para {ent}', 'Licencias por 12 meses con soporte técnico y capacitación.', [30000, 260000]],
    ],
    'alimentos' => [
        ['Suministro de alimentos para refacción escolar, {lugar}', 'Víveres frescos y secos para programa de alimentación escolar, entrega semanal.', [60000, 900000]],
        ['Compra de agua pura en garrafón para {ent}', 'Suministro de agua purificada en garrafones de 5 galones y dispensadores.', [8000, 60000]],
        ['Servicio de alimentación para eventos de {ent}', 'Almuerzos y refacciones para talleres y reuniones durante el año.', [20000, 190000]],
    ],
    'limpieza' => [
        ['Servicio de limpieza y mantenimiento de edificios de {ent}', 'Limpieza diaria, jardinería y fumigación de instalaciones, incluye insumos.', [50000, 420000]],
        ['Suministro de productos de limpieza para {ent}', 'Cloro, desinfectante, escobas, trapeadores, papel higiénico y bolsas.', [12000, 100000]],
    ],
    ];
}
function demo_entities(): array {
    return ['Municipalidad de {l}', 'Ministerio de Educación - DIDEDUC {l}', 'Hospital Nacional de {l}', 'Centro de Salud de {l}', 'Ministerio de Comunicaciones (CIV)', 'Dirección de Área de Salud de {l}', 'Gobernación Departamental de {l}', 'Universidad de San Carlos - CUNOC', 'Policía Nacional Civil - Región {l}', 'Municipalidad de San Pedro de {l}'];
}
function demo_places(): array {
    return ['Escuintla' => ['Escuintla','Santa Lucía Cotzumalguapa','Masagua'], 'Guatemala' => ['Mixco','Villa Nueva','San Miguel Petapa'], 'Quetzaltenango' => ['Quetzaltenango','Salcajá','Olintepeque'], 'Alta Verapaz' => ['Cobán','San Cristóbal Verapaz','Tactic'], 'Huehuetenango' => ['Huehuetenango','Chiantla','Jacaltenango'], 'Petén' => ['Flores','San Benito','Poptún'], 'Izabal' => ['Puerto Barrios','Morales','Livingston'], 'Chiquimula' => ['Chiquimula','Esquipulas','Jocotán'], 'San Marcos' => ['San Marcos','Malacatán','Tecún Umán'], 'Sacatepéquez' => ['Antigua Guatemala','Ciudad Vieja','Jocotenango'], 'Santa Rosa' => ['Cuilapa','Barberena','Chiquimulilla'], 'Suchitepéquez' => ['Mazatenango','Cuyotenango','Patulul'], 'Retalhuleu' => ['Retalhuleu','Champerico','San Sebastián'], 'Jutiapa' => ['Jutiapa','Asunción Mita','Jalpatagua'], 'Quiché' => ['Santa Cruz del Quiché','Chichicastenango','Nebaj'], 'Zacapa' => ['Zacapa','Gualán','Río Hondo']];
}
function demo_requirements(string $mod): string {
    $base = ["Constancia de inscripción en el Registro General de Adquirientes y Enajenantes (RGAE)", "Constancia de inscripción en el RTU y NIT actualizado", "Patente de comercio de empresa y de sociedad (si aplica)", "Solvencia fiscal vigente o constancia de inscripción en SAT", "Fotocopia legalizada del DPI del representante legal", "Declaración jurada de no tener impedimento para contratar con el Estado", "Oferta económica firmada y sellada (cuadro de precios)"];
    if ($mod === 'licitacion') { $base[] = "Fianza de sostenimiento de oferta (1% del monto)"; $base[] = "Constancia de inscripción en el Registro de Precalificados"; $base[] = "Carta de experiencia en proyectos similares (mínimo 2)"; }
    if ($mod === 'cotizacion') { $base[] = "Carta de oferta con vigencia mínima de 30 días"; }
    return implode("\n", $base);
}
/** Genera concursos demo realistas, con fechas relativas a hoy. $open = abiertos; $awarded = adjudicados (para precios). */
function demo_generate(int $open, string $key, int $awarded = 0): array {
    mt_srand(crc32($key));
    $cat = demo_catalog(); $places = demo_places(); $ents = demo_entities(); $mods = array_keys(modalidades());
    $pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
    $out = []; $total = $open + $awarded;
    $cats = array_keys($cat);
    for ($i = 0; $i < $total; $i++) {
        $c = $cats[$i % count($cats)]; $tpl = $pick($cat[$c]);
        $keys = array_keys($places); $pool = array_merge($keys, ['Guatemala','Guatemala','Guatemala','Escuintla','Escuintla','Quetzaltenango','Alta Verapaz','Petén','Izabal']); $dep = $pool[mt_rand(0, count($pool) - 1)]; $lugar = $pick($places[$dep]);
        $entTpl = $pick($ents); $ent = str_replace('{l}', $lugar, $entTpl);
        $amount = round(mt_rand($tpl[2][0], $tpl[2][1]) / 50) * 50;
        $mod = $amount < 90000 ? ($i % 2 ? 'compra_directa' : 'cotizacion') : ($amount < 400000 ? ($i % 3 ? 'cotizacion' : 'licitacion') : ($i % 4 ? 'licitacion' : 'contrato_abierto'));
        if (mt_rand(0, 9) === 0) $mod = 'contrato_abierto';
        $isAw = $i >= $open;
        $title = str_replace(['{lugar}', '{ent}', '{n}'], [$lugar, $ent, mt_rand(2, 9)], $tpl[0]);
        $desc = str_replace(['{lugar}', '{ent}', '{n}'], [$lugar, $ent, mt_rand(2, 9)], $tpl[1]) . ' Concurso de ejemplo (DEMO) generado para demostrar la plataforma.';
        $pub = $isAw ? date('Y-m-d', strtotime('-' . mt_rand(15, 220) . ' days')) : date('Y-m-d', strtotime('-' . mt_rand(0, 6) . ' days'));
        $t = ['ext_id' => 'DEMO-' . substr(md5($key . $i), 0, 8), 'title' => $title, 'description' => $desc, 'entity' => $ent, 'department' => $dep, 'modality' => $mod, 'category' => $c, 'amount' => $amount,
            'publish_date' => $pub, 'requirements' => demo_requirements($mod), 'url' => 'https://www.guatecompras.gt/'];
        if ($isAw) {
            $t['status'] = 'adjudicado'; $t['deadline'] = date('Y-m-d H:i:s', strtotime($pub . ' +' . mt_rand(8, 20) . ' days 10:00'));
            $t['awarded_amount'] = round($amount * (mt_rand(78, 100) / 100) / 10) * 10;
            $t['awarded_to'] = $pick(['Comercial El Quetzal, S.A.', 'Distribuidora Maya, S.A.', 'Servicios Integrales del Sur', 'Ferretería La Económica', 'Grupo Constructor Kaqchikel', 'Suministros y Equipos GT', 'Proveedora del Norte, S.A.', 'Inversiones Tikal']);
        } else {
            $t['status'] = 'abierto'; $t['deadline'] = date('Y-m-d H:i:s', strtotime('+' . mt_rand(1, 38) . ' days ' . mt_rand(8, 16) . ':00'));
        }
        $out[] = $t;
    }
    mt_srand();
    return $out;
}

function seed_defaults(): void {
    // Planes
    if (!val("SELECT COUNT(*) FROM plans")) {
        $pl = [['Inicio', 99, 30, 8, 60, "8 palabras clave\n60 alertas al mes\nBorrador de oferta (plantilla)\nPrecios de referencia", 0, 1], ['Profesional', 199, 30, 25, 300, "25 palabras clave\n300 alertas al mes\nBorradores con IA (si está activa)\nPrecios de referencia\nAlertas por WhatsApp", 1, 2], ['Empresa', 449, 30, 80, 1500, "80 palabras clave\nAlertas ilimitadas en la práctica\nTodas las funciones\nSoporte prioritario por WhatsApp", 0, 3]];
        foreach ($pl as $p) insert('plans', ['name' => $p[0], 'price' => $p[1], 'period_days' => $p[2], 'kw_limit' => $p[3], 'alert_limit' => $p[4], 'features' => $p[5], 'highlight' => $p[6], 'sort_order' => $p[7]]);
    }
    // Plantillas
    $tp = [
     'welcome' => ['Bienvenida', 'Bienvenido a {sitio}', "Hola {nombre},\n\nTu cuenta está lista y tienes una prueba gratis activa. Completa el asistente de 3 pasos para empezar a recibir oportunidades:\n\n{enlace}\n\nSi necesitas ayuda, responde este correo."],
     'verify' => ['Verificar correo', 'Confirma tu correo en {sitio}', "Hola {nombre},\n\nConfirma tu correo con este enlace (vale por 48 horas):\n\n{enlace}\n\nSi no creaste la cuenta, ignora este mensaje."],
     'reset' => ['Recuperar contraseña', 'Recupera tu contraseña de {sitio}', "Hola {nombre},\n\nRecibimos una solicitud para cambiar tu contraseña. Usa este enlace (vale por 1 hora y solo una vez):\n\n{enlace}\n\nSi no fuiste tú, ignora este mensaje."],
     'alert_email' => ['Alerta de oportunidades (correo)', '{cantidad} oportunidad(es) nueva(s) para ti', "Hola {nombre},\n\nEncontramos {cantidad} concurso(s) que le sirven a tu empresa:\n\n{lista}\n\nVer todas en tu panel: {panel}"],
     'alert_wa' => ['Alerta de oportunidades (WhatsApp)', '', "Hola {nombre}, {sitio} encontró {cantidad} concurso(s) para ti:\n{lista}\n\nPanel: {panel}"],
     'renewal_3d' => ['Renovación en 3 días', 'Tu plan vence en pocos días', "Hola {nombre},\n\nTu plan {plan} vence el {fecha}. Renueva para no perder oportunidades:\n\n{enlace}"],
     'renewal_1d' => ['Renovación mañana', 'Tu plan vence mañana', "Hola {nombre},\n\nTu plan {plan} vence el {fecha}. Renueva hoy para seguir recibiendo alertas:\n\n{enlace}"],
     'expired' => ['Plan vencido', 'Tu plan venció', "Hola {nombre},\n\nTu plan {plan} venció el {fecha}. Tus datos están guardados; renueva cuando quieras:\n\n{enlace}"],
     'payment_received' => ['Pago recibido', 'Recibimos tu comprobante', "Hola {nombre},\n\nRecibimos tu comprobante de pago. Lo revisaremos y activaremos tu plan pronto."],
     'payment_approved' => ['Pago aprobado', 'Tu plan está activo', "Hola {nombre},\n\nTu pago fue aprobado. Plan {plan} activo hasta el {fecha}. ¡Gracias!"],
    ];
    foreach ($tp as $k => $t) q("INSERT IGNORE INTO templates (k,label,subject,body) VALUES (?,?,?,?)", [$k, $t[0], $t[1], $t[2]]);
    // Sinónimos
    $sy = [['tuberia', 'tubo, pvc, cañeria, tuberias'], ['medicamento', 'medicina, farmaco, insumo medico'], ['combustible', 'diesel, gasolina, gas'], ['util escolar', 'cuaderno, papeleria, libreria, lapiz'], ['computadora', 'laptop, equipo de computo, pc'], ['limpieza', 'aseo, desinfectante, higiene'], ['cemento', 'concreto, hormigon, block'], ['vigilancia', 'seguridad, guardia'], ['alimento', 'refaccion, vivere, comida']];
    foreach ($sy as $s) q("INSERT IGNORE INTO synonyms (term,synonyms) VALUES (?,?)", [$s[0], $s[1]]);
    // FAQs
    if (!val("SELECT COUNT(*) FROM faqs")) {
        $f = [['¿De dónde salen los concursos?', 'De los datos abiertos que publica GUATECOMPRAS (formato OCDS) y de cargas que hace nuestro equipo. Siempre te damos el enlace oficial para verificar cada concurso.'],
        ['¿Me garantizan que voy a ganar?', 'No. Licitador informa y asiste en la preparación de ofertas; no garantiza adjudicaciones ni ofrece gestión de influencias.'],
        ['¿Necesito saber de tecnología?', 'No. Te preguntamos qué vendes y dónde trabajas, y te mostramos solo lo que te sirve, con los plazos claros.'],
        ['¿Cómo pago la suscripción?', 'Por transferencia o depósito bancario. Subes tu comprobante y activamos tu plan. También podemos compartirte un enlace de pago.'],
        ['¿Puedo cancelar cuando quiera?', 'Sí. No hay contratos forzosos: tu plan simplemente no se renueva si no pagas el siguiente mes.'],
        ['¿Cómo funciona el borrador de oferta?', 'Armamos una carta de oferta, la lista de documentos y un cuadro de precios con los datos del concurso y de tu empresa. Siempre es un borrador que debes revisar antes de presentarlo.'],
        ['¿Recibo alertas por WhatsApp?', 'Sí. Puedes recibir un mensaje con los concursos nuevos, o abrir el mensaje listo con un clic desde tu panel.']];
        foreach ($f as $i => $x) insert('faqs', ['question' => $x[0], 'answer' => $x[1], 'sort_order' => $i]);
    }
    // Sectores
    if (!val("SELECT COUNT(*) FROM sectors")) {
        $s = [['Construcción y obra', 'construccion', 'Caminos, muros, drenajes y remodelaciones municipales.', 'construccion, obra, pavimento, drenaje'], ['Transporte y combustibles', 'transporte', 'Combustible, llantas, mantenimiento y flete.', 'combustible, diesel, transporte'], ['Insumos médicos', 'medico', 'Medicamentos, material de curación y equipo.', 'medicamento, insumo medico, jeringa'], ['Útiles escolares', 'escolar', 'Cuadernos, mobiliario y librería para escuelas.', 'util escolar, cuaderno, papeleria'], ['Ferretería y materiales', 'ferreteria', 'Tuberías PVC, cemento, hierro, pintura y herramientas.', 'tuberia, pvc, cemento, hierro'], ['Servicios', 'servicios', 'Seguridad, consultoría, capacitación e impresión.', 'servicio, consultoria, seguridad']];
        foreach ($s as $i => $x) insert('sectors', ['name' => $x[0], 'slug' => $x[1], 'tagline' => $x[2], 'keywords' => $x[3], 'image' => 'img/sector-' . $x[1] . '.svg', 'sort_order' => $i]);
    }
    // Páginas legales
    $pg = [
     'terminos' => ['Términos y condiciones', "<h2>1. Qué es Licitador</h2><p>Licitador es un servicio de suscripción que informa sobre concursos de compras del Estado de Guatemala publicados en GUATECOMPRAS y asiste en la preparación de borradores de oferta.</p><h2>2. Lo que no hacemos</h2><p><strong>Licitador no garantiza adjudicaciones ni ofrece gestión de influencias; solo informa y asiste en la preparación de ofertas.</strong> La información debe verificarse siempre en el sitio oficial de GUATECOMPRAS. Los borradores son un apoyo y deben ser revisados por el usuario.</p><h2>3. Suscripción y pagos</h2><p>El acceso se activa por periodos pagados. Los pagos se registran tras verificar el comprobante. Al vencer el periodo, el acceso a las alertas se suspende hasta renovar.</p><h2>4. Uso aceptable</h2><p>Eres responsable de la veracidad de los datos de tu empresa y de las ofertas que presentes.</p><h2>5. Cambios</h2><p>Podemos actualizar estos términos; publicaremos la fecha de la última revisión.</p>"],
     'privacidad' => ['Política de privacidad', "<h2>Datos que recogemos</h2><p>Nombre, correo, teléfono, datos de tu empresa y preferencias de búsqueda, necesarios para enviarte oportunidades.</p><h2>Cómo los usamos</h2><p>Para calcular coincidencias, enviarte alertas, gestionar tu suscripción y dar soporte. No vendemos tus datos.</p><h2>Servicios de terceros</h2><p>Si el administrador activa la función de IA, el texto del concurso y datos básicos de tu perfil pueden enviarse al proveedor de IA para redactar borradores.</p><h2>Tus derechos</h2><p>Puedes pedir acceso, corrección o eliminación de tus datos escribiéndonos al correo de contacto.</p>"],
     'aviso' => ['Aviso importante', "<p><strong>Licitador no garantiza adjudicaciones ni ofrece gestión de influencias; solo informa y asiste en la preparación de ofertas.</strong></p><p>Los datos mostrados provienen de fuentes públicas y de cargas manuales; pueden contener errores o desactualizaciones. Verifica siempre en GUATECOMPRAS antes de ofertar. Los concursos marcados como DEMO son ejemplos ficticios.</p>"],
    ];
    foreach ($pg as $slug => $p) q("INSERT IGNORE INTO pages (slug,title,body) VALUES (?,?,?)", [$slug, $p[0], $p[1]]);
    // Ajustes por defecto
    $st = ['site_name' => 'Licitador', 'tagline' => 'El radar de compras del Estado para tu negocio', 'hero_title' => 'Cada día, los concursos del Estado que sí te sirven', 'hero_sub' => 'Dinos qué vendes y dónde trabajas. Licitador revisa GUATECOMPRAS por ti, te avisa solo lo importante y te ayuda a preparar tu oferta.',
        'cta_text' => 'Probar 7 días gratis', 'trial_days' => '7', 'timezone' => 'America/Guatemala', 'min_score' => '45', 'digest_hour' => '7', 'source_type' => 'demo', 'wa_provider' => 'manual', 'accent_new' => '#2FBF8F', 'accent_urgent' => '#C2334D', 'gold' => '#C9A24B',
        'contact_email' => '', 'contact_phone' => '', 'contact_whatsapp' => '', 'seo_title' => 'Licitador · Concursos del Estado de Guatemala para tu negocio', 'seo_desc' => 'Recibe cada día los concursos de GUATECOMPRAS que le sirven a tu empresa, con plazos, requisitos, precios de referencia y borrador de oferta.',
        'how1_t' => 'Cuéntanos qué vendes', 'how1_d' => 'Palabras clave, departamentos, rango de montos y modalidades. Tarda 2 minutos.', 'how2_t' => 'Revisamos por ti, cada día', 'how2_d' => 'Cruzamos los concursos publicados con tu perfil y te explicamos por qué coinciden.', 'how3_t' => 'Ofertas con ventaja', 'how3_d' => 'Plazos, requisitos, precios de adjudicados parecidos y un borrador de oferta listo para revisar.',
        'problem_t' => 'Buscar a mano te cuesta tiempo y oportunidades', 'problem_d' => 'Miles de concursos se publican al mes. Los pequeños negocios no tienen quien los revise a diario y se enteran tarde, cuando el plazo ya venció.',
        'bank_info' => "Banco: (configura en Admin → Ajustes)\nCuenta: \nA nombre de: ", 'footer_text' => 'Licitador no garantiza adjudicaciones ni ofrece gestión de influencias; solo informa y asiste en la preparación de ofertas.', 'ai_model' => 'claude-sonnet-5-5', 'trial_alert_limit' => '50'];
    foreach ($st as $k => $v) q("INSERT IGNORE INTO settings (k,v) VALUES (?,?)", [$k, $v]);
    settings_all(true);
}
function seed_demo_tenders(): int {
    $list = array_merge(demo_generate(70, 'inicial', 50));
    [$n] = import_tenders($list, true);
    return $n;
}
