#!/usr/bin/env python3
"""Genera site/_core/seed/geo.json: páginas SEO locales (municipios y zonas) y metadatos SEO de las páginas existentes.
Reglas: descripción SIEMPRE empieza con «Tel: 3556-7904», title <= 60, description <= 160, un solo H1 (la portada),
H2/H3 en el cuerpo, sin datos inventados (solo teléfono, Q250, 24/7 y hechos geográficos verificados).
Uso: python3 tools/build_geo.py
"""
import json, os, re, unicodedata

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEL = '3556-7904'
PRE = 'Tel: ' + TEL


def slug(s):
    s = unicodedata.normalize('NFD', s.lower())
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


# key, nombre, nombre completo (título/H1), departamento, km en línea recta desde la capital, contexto, situación
M = [
    ('guatemala', 'la ciudad de Guatemala', 'Ciudad de Guatemala', 'Guatemala', 0,
     'La ciudad de Guatemala es el punto desde donde sale buena parte de nuestro trabajo: 22 zonas urbanas, el Anillo Periférico, el Boulevard Liberación y las calzadas que cruzan la ciudad de lado a lado.',
     'Un carro que se apaga en plena hora pico, un choque leve en una calzada o un vehículo que no arranca en un parqueo son llamadas diarias. En esos casos lo urgente es sacarlo de la vía y llevarlo al taller o a la casa del dueño.'),
    ('santa-catarina-pinula', 'Santa Catarina Pinula', 'Santa Catarina Pinula', 'Guatemala', 7,
     'Santa Catarina Pinula colinda con la capital por el lado sureste y queda a unos 7 km en línea recta del centro. Su movimiento se concentra en la salida hacia la carretera a El Salvador.',
     'Entre el tráfico de entrada y salida de la ciudad y las calles de colonia, un vehículo averiado suele quedar lejos de un taller. Ahí la grúa evita que el problema se alargue.'),
    ('chinautla', 'Chinautla', 'Chinautla', 'Guatemala', 8,
     'Chinautla está al norte de la ciudad, a unos 8 km en línea recta del centro, y comparte límite con las zonas del norte de la capital.',
     'Por el volumen de tráfico que entra y sale hacia la capital, es común que un carro o un camión se quede varado en plena vía. Con una llamada enviamos la grúa a donde esté el vehículo.'),
    ('mixco', 'Mixco', 'Mixco', 'Guatemala', 11,
     'Mixco es uno de los municipios más poblados del país y está al occidente de la capital, a unos 11 km en línea recta del centro. Tiene once zonas y varias vías de acceso a la ciudad, entre ellas la Interamericana (CA-1 Occidente).',
     'En Mixco hay mucho tráfico diario, pendientes y calles angostas de colonia. Un vehículo que no puede continuar bloquea rápido, por eso conviene sacarlo cuanto antes con una grúa.'),
    ('san-jose-pinula', 'San José Pinula', 'San José Pinula', 'Guatemala', 14,
     'San José Pinula queda al oriente, a unos 14 km en línea recta de la capital, sobre el corredor de la carretera a El Salvador (CA-1 Oriente).',
     'Quien viaja entre la capital y el oriente del país pasa por aquí. Si el carro se descompone en el camino, no hace falta caminar en busca de ayuda: llame y vamos por él.'),
    ('villa-nueva', 'Villa Nueva', 'Villa Nueva', 'Guatemala', 15,
     'Villa Nueva está al sur de la capital, a unos 15 km en línea recta, y se organiza en doce zonas. Su eje principal es la CA-9 Sur, que la une con la ciudad y con Escuintla.',
     'Con tanto tráfico en la CA-9 y en las zonas de mayor movimiento, los remolques de vehículos livianos y pesados son parte de lo que atendemos cada semana en Villa Nueva.'),
    ('san-pedro-sacatepequez', 'San Pedro Sacatepéquez', 'San Pedro Sacatepéquez', 'Guatemala', 16,
     'San Pedro Sacatepéquez, en el departamento de Guatemala, está a unos 16 km en línea recta de la capital hacia el noroccidente. Se divide en cuatro zonas: Morazán, San José, Ermita y Barrios.',
     'Es un municipio de calles de pueblo y carreteras de salida. Cuando un vehículo no arranca o queda atascado, enviamos la grúa a la zona donde se encuentre.'),
    ('san-miguel-petapa', 'San Miguel Petapa', 'San Miguel Petapa', 'Guatemala', 16,
     'San Miguel Petapa está al sur de la capital, a unos 16 km en línea recta, pegado a Villa Nueva y cerca de la CA-9 Sur.',
     'Entre las entradas hacia Villa Nueva y la ciudad, un carro varado puede detener el paso de una calle entera. Una grúa a tiempo despeja la vía.'),
    ('san-lucas-sacatepequez', 'San Lucas Sacatepéquez', 'San Lucas Sacatepéquez', 'Sacatepéquez', 16,
     'San Lucas Sacatepéquez está sobre la carretera Interamericana (CA-1 Occidente), a unos 16 km en línea recta de la capital, ya en el departamento de Sacatepéquez y camino a Antigua Guatemala.',
     'En la ruta hacia Antigua y el occidente, los vehículos con fallas mecánicas o de frenos no pueden seguir por su cuenta. Los remolcamos al taller o hacia donde necesiten.'),
    ('palencia', 'Palencia', 'Palencia, Guatemala', 'Guatemala', 17,
     'Palencia, en el departamento de Guatemala, queda al nororiente de la capital, a unos 17 km en línea recta, en la zona de la Ruta al Atlántico.',
     'Hay tramos largos y poco poblados donde nadie pasa a ayudar. Con un teléfono y una ubicación, llegamos hasta donde esté el vehículo.'),
    ('san-pedro-ayampuc', 'San Pedro Ayampuc', 'San Pedro Ayampuc', 'Guatemala', 17,
     'San Pedro Ayampuc está al norte de la capital, a unos 17 km en línea recta del centro, en el departamento de Guatemala.',
     'Si el vehículo se queda en un camino de acceso o en la carretera, damos servicio de remolque terrestre sin importar la hora.'),
    ('villa-canales', 'Villa Canales', 'Villa Canales', 'Guatemala', 17,
     'Villa Canales queda al sur de la capital, a unos 17 km en línea recta del centro, hacia el lago de Amatitlán.',
     'Con calles de colonia, residenciales y tramos de carretera de salida, atendemos desde el carro particular hasta el camión de carga.'),
    ('san-raimundo', 'San Raimundo', 'San Raimundo', 'Guatemala', 17,
     'San Raimundo está al norte de la capital, a unos 17 km en línea recta, en el departamento de Guatemala. Una de sus rutas conecta con Chinautla y el norte de la ciudad.',
     'Los caminos de salida, con curvas y pendientes, no perdonan una falla de frenos o un recalentamiento. Si le pasa, llame y enviamos la grúa.'),
    ('san-juan-sacatepequez', 'San Juan Sacatepéquez', 'San Juan Sacatepéquez', 'Guatemala', 18,
     'San Juan Sacatepéquez es un municipio extenso del departamento de Guatemala, al noroccidente de la capital, a unos 18 km en línea recta del centro.',
     'Con tantas aldeas y caminos alejados del casco urbano, a un vehículo que se queda detenido le cuesta conseguir ayuda. Aquí sí vamos hasta donde esté.'),
    ('santiago-sacatepequez', 'Santiago Sacatepéquez', 'Santiago Sacatepéquez', 'Sacatepéquez', 18,
     'Santiago Sacatepéquez, en el departamento de Sacatepéquez, está al occidente de la capital, a unos 18 km en línea recta.',
     'Para quien viaja entre la capital y los municipios de Sacatepéquez, una grúa de confianza evita que una avería termine en horas de espera.'),
    ('san-bartolome-milpas-altas', 'San Bartolomé Milpas Altas', 'San Bartolomé Milpas Altas', 'Sacatepéquez', 19,
     'San Bartolomé Milpas Altas es parte del grupo de municipios de Milpas Altas, en Sacatepéquez, a unos 19 km en línea recta de la capital.',
     'Las calles inclinadas y estrechas de la zona dificultan maniobrar un vehículo averiado. Nuestras grúas están pensadas para sacarlo con cuidado.'),
    ('santa-lucia-milpas-altas', 'Santa Lucía Milpas Altas', 'Santa Lucía Milpas Altas', 'Sacatepéquez', 19,
     'Santa Lucía Milpas Altas, en Sacatepéquez, queda a unos 19 km en línea recta de la capital. Su casco urbano está dividido en cuatro zonas y la zona 2 concentra la actividad industrial.',
     'Con industria y transporte pesado en la zona, también atendemos camiones y vehículos de carga que necesiten remolque.'),
    ('fraijanes', 'Fraijanes', 'Fraijanes', 'Guatemala', 20,
     'Fraijanes está al sureste de la capital, a unos 20 km en línea recta, en el departamento de Guatemala, sobre el corredor de la carretera a El Salvador.',
     'Quienes bajan o suben por esta ruta lo saben: una falla en plena cuesta es un riesgo. Llámenos y sacamos el vehículo de ahí.'),
    ('chuarrancho', 'Chuarrancho', 'Chuarrancho', 'Guatemala', 20,
     'Chuarrancho es un municipio rural del norte del departamento de Guatemala, a unos 20 km en línea recta de la capital.',
     'Con caminos largos y pocas opciones de ayuda, un vehículo varado puede esperar mucho tiempo. Avísenos dónde está y lo trasladamos.'),
    ('san-jose-del-golfo', 'San José del Golfo', 'San José del Golfo', 'Guatemala', 20,
     'San José del Golfo está al norte de la capital, a unos 20 km en línea recta, en el departamento de Guatemala.',
     'Si su carro o camión no puede continuar, no tiene por qué dejarlo botado. Lo remolcamos hasta el taller o hasta donde indique.'),
    ('magdalena-milpas-altas', 'Magdalena Milpas Altas', 'Magdalena Milpas Altas', 'Sacatepéquez', 20,
     'Magdalena Milpas Altas es otro de los municipios de Milpas Altas, en Sacatepéquez, a unos 20 km en línea recta de la capital.',
     'En calles de pendiente fuerte, un vehículo sin frenos o sin motor se complica rápido. Llame y enviamos la grúa.'),
    ('santo-domingo-xenacoj', 'Santo Domingo Xenacoj', 'Santo Domingo Xenacoj', 'Sacatepéquez', 21,
     'Santo Domingo Xenacoj, en Sacatepéquez, está a unos 21 km en línea recta de la capital hacia el noroccidente.',
     'Si el vehículo se detiene en el camino, damos servicio de remolque terrestre, de día o de madrugada.'),
    ('amatitlan', 'Amatitlán', 'Amatitlán', 'Guatemala', 22,
     'Amatitlán queda junto al lago del mismo nombre, al sur de la capital, a unos 22 km en línea recta, sobre el corredor de la CA-9 Sur.',
     'Entre el tránsito de paso hacia Escuintla y el movimiento propio del municipio, los varamientos son frecuentes. Atendemos desde carros livianos hasta camiones.'),
    ('sumpango', 'Sumpango', 'Sumpango', 'Sacatepéquez', 25,
     'Sumpango, en Sacatepéquez, está a unos 25 km en línea recta de la capital, sobre la ruta hacia el occidente del país.',
     'Para quienes viajan por la Interamericana, un carro varado en Sumpango no tiene que esperar horas. Llame y vamos por él.'),
    ('jocotenango', 'Jocotenango', 'Jocotenango', 'Sacatepéquez', 26,
     'Jocotenango, en Sacatepéquez, es vecino inmediato de Antigua Guatemala y queda a unos 26 km en línea recta de la capital.',
     'Si va o viene de Antigua y el carro falla, no necesita improvisar. Enviamos la grúa a su ubicación.'),
    ('santa-maria-de-jesus', 'Santa María de Jesús', 'Santa María de Jesús', 'Sacatepéquez', 27,
     'Santa María de Jesús queda en las faldas del volcán de Agua, en Sacatepéquez, a unos 27 km en línea recta de la capital.',
     'Con carreteras de pendiente y curvas, los problemas de frenos o recalentamiento son comunes. Le ayudamos a sacar el vehículo con seguridad.'),
    ('pastores', 'Pastores', 'Pastores', 'Sacatepéquez', 27,
     'Pastores, en Sacatepéquez, está a unos 27 km en línea recta de la capital, junto a Jocotenango y Antigua Guatemala.',
     'Es un municipio de paso para muchos vehículos de trabajo. Si uno queda detenido, damos servicio de remolque sin importar el día.'),
    ('san-antonio-la-paz', 'San Antonio La Paz', 'San Antonio La Paz', 'El Progreso', 28,
     'San Antonio La Paz pertenece al departamento de El Progreso, a unos 28 km en línea recta de la capital hacia el nororiente.',
     'Aunque está fuera del departamento de Guatemala, también llegamos hasta ahí para remolcar el vehículo que lo necesite.'),
    ('san-vicente-pacaya', 'San Vicente Pacaya', 'San Vicente Pacaya', 'Escuintla', 28,
     'San Vicente Pacaya, en Escuintla, es el municipio desde donde se sube al volcán de Pacaya y queda a unos 28 km en línea recta de la capital.',
     'Con caminos de subida y bajada, un vehículo con problemas mecánicos necesita ayuda profesional. Allá también vamos.'),
    ('ciudad-vieja', 'Ciudad Vieja', 'Ciudad Vieja', 'Sacatepéquez', 30,
     'Ciudad Vieja está al pie del volcán de Agua, en Sacatepéquez, a unos 30 km en línea recta de la capital.',
     'Si su vehículo falló en la zona, no tiene que moverse de ahí: llame y llegamos para remolcarlo.'),
    ('el-tejar', 'El Tejar', 'El Tejar, Chimaltenango', 'Chimaltenango', 31,
     'El Tejar pertenece al departamento de Chimaltenango y queda a unos 31 km en línea recta de la capital, cerca de la cabecera departamental.',
     'Atendemos esta zona con el mismo servicio de remolque terrestre que en el resto de la región central.'),
    ('san-antonio-aguas-calientes', 'San Antonio Aguas Calientes', 'San Antonio Aguas Calientes', 'Sacatepéquez', 31,
     'San Antonio Aguas Calientes, en Sacatepéquez, está al occidente de Antigua Guatemala y a unos 31 km en línea recta de la capital.',
     'Si su vehículo queda detenido en la zona, lo recogemos y lo llevamos hasta el taller o hasta su casa.'),
    ('santa-cruz-naranjo', 'Santa Cruz Naranjo', 'Santa Cruz Naranjo', 'Santa Rosa', 31,
     'Santa Cruz Naranjo pertenece al departamento de Santa Rosa y queda a unos 31 km en línea recta de la capital.',
     'Cubrimos también estos municipios fuera del departamento de Guatemala. Llame y confirmamos cómo llegar hasta usted.'),
    ('granados', 'Granados', 'Granados, Baja Verapaz', 'Baja Verapaz', 31,
     'Granados es un municipio de Baja Verapaz, a unos 31 km en línea recta de la capital hacia el norte.',
     'Hay tramos largos y pocas opciones de ayuda mecánica. Si su vehículo se detiene, avísenos y lo trasladamos.'),
    ('santa-catarina-barahona', 'Santa Catarina Barahona', 'Santa Catarina Barahona', 'Sacatepéquez', 31,
     'Santa Catarina Barahona, en Sacatepéquez, queda a unos 31 km en línea recta de la capital.',
     'Si un vehículo falla en este municipio, lo remolcamos con equipo adecuado para su tamaño y peso.'),
    ('parramos', 'Parramos', 'Parramos', 'Chimaltenango', 32,
     'Parramos pertenece al departamento de Chimaltenango y queda a unos 32 km en línea recta de la capital.',
     'Si necesita mover un vehículo liviano, pesado o maquinaria en la zona, llame para coordinar el servicio.'),
]

# zonas confirmadas
ZONAS = {
    'guatemala': [str(i) for i in range(1, 20)] + ['21', '24', '25'],
    'mixco': [str(i) for i in range(1, 12)],
    'villa-nueva': [str(i) for i in range(1, 13)],
    'san-pedro-sacatepequez': ['1', '2', '3', '4'],
    'santa-lucia-milpas-altas': ['1', '2', '3', '4'],
}
# páginas propias de zona (con demanda detectada en Google Guatemala)
ZONA_PAGES = {
    'guatemala': ['1', '4', '5', '6', '7', '10', '11', '12', '18'],
    'mixco': ['4', '6', '8', '11'],
}
ZONA_FACT = {
    ('guatemala', '1'): 'La zona 1 es el centro histórico de la ciudad, con la Plaza Central y el Palacio Nacional. Las calles son angostas y hay movimiento a toda hora, así que un vehículo detenido bloquea rápido.',
    ('guatemala', '4'): 'La zona 4 queda junto al centro de la ciudad y reúne oficinas, comercios y talleres, incluida el área de 4 Grados Norte. El tráfico se carga en horas pico.',
    ('guatemala', '5'): 'En la zona 5 está el estadio Doroteo Guamuch Flores. Los días de partido y de eventos el tránsito se complica, y un carro varado lo empeora.',
    ('guatemala', '6'): 'La zona 6 está al norte de la capital y es una de las salidas hacia Chinautla. Hay mucho tráfico de carga y transporte.',
    ('guatemala', '7'): 'La zona 7 es una de las más extensas de la ciudad. Ahí está el sitio arqueológico de Kaminaljuyú, junto a colonias, comercios y avenidas de mucho flujo.',
    ('guatemala', '10'): 'La zona 10, conocida como Zona Viva, concentra hoteles, restaurantes y oficinas. Con parqueos llenos y calles con poco espacio, remolcar bien un vehículo requiere cuidado.',
    ('guatemala', '11'): 'En la zona 11 están el centro comercial Miraflores y la Calzada Roosevelt, una de las vías más transitadas de la capital.',
    ('guatemala', '12'): 'La zona 12 es sede del campus central de la Universidad de San Carlos y tiene a la avenida Petapa como eje hacia el sur de la ciudad.',
    ('guatemala', '18'): 'La zona 18 queda al nororiente de la capital, hacia la Ruta al Atlántico, y es de las más pobladas de la ciudad.',
    ('mixco', '4'): 'La zona 4 de Mixco forma parte del casco urbano del municipio, que crece hacia el occidente de la capital.',
    ('mixco', '6'): 'La zona 6 de Mixco es parte de un municipio con once zonas y mucho tránsito diario hacia la capital.',
    ('mixco', '8'): 'En la zona 8 de Mixco está Ciudad San Cristóbal, una de las áreas más pobladas del municipio.',
    ('mixco', '11'): 'La zona 11 de Mixco es la última de las once zonas del municipio, hacia el occidente de la capital.',
}

by_key = {m[0]: m for m in M}
order = [m[0] for m in M]


def title_fit(cands):
    for c in cands:
        if len(c) <= 60:
            return c
    raise ValueError('title >60: ' + cands[-1])


def desc_fit(cands):
    for c in cands:
        if len(c) <= 160:
            return c
    raise ValueError('desc >160: ' + cands[-1] + ' ' + str(len(cands[-1])))


def nearest(key, n=7):
    i = order.index(key)
    pool = [k for k in order if k != key]
    pool.sort(key=lambda k: abs(order.index(k) - i))
    near = pool[:n]
    if 'guatemala' not in near and key != 'guatemala':
        near = ['guatemala'] + near[:-1]
    return near


def mpath(k):
    return '/gruas-' + ('ciudad-de-guatemala' if k == 'guatemala' else slug(by_key[k][1])) + '/'


def zpath(k, z):
    return '/gruas-zona-%s-%s/' % (z, 'ciudad-de-guatemala' if k == 'guatemala' else slug(by_key[k][1]))


def mlabel(k):
    return 'Grúas en ' + ('la ciudad de Guatemala' if k == 'guatemala' else by_key[k][1])


def zone_name(k, z):
    return 'zona %s de %s' % (z, 'la ciudad de Guatemala' if k == 'guatemala' else by_key[k][1])


def faq(en, v):
    q1 = '¿Cuánto cuesta una grúa %s?' % en
    a1 = ['Las tarifas de Grúas Express Towing inician en Q250. El monto final depende del tipo de vehículo y de la distancia, así que lo más rápido es llamar al %s, decir dónde está y hacia dónde va, y le confirmamos el precio.' % TEL,
          'Desde Q250. El precio exacto cambia según el vehículo y el recorrido; llame o escriba por WhatsApp al %s con esos dos datos y le respondemos.' % TEL,
          'El servicio parte de Q250. Para darle el monto final necesitamos saber qué vehículo es y a dónde se va a llevar; puede decírnoslo por teléfono o por WhatsApp al %s.' % TEL][v]
    q2 = '¿Hay grúas disponibles las 24 horas %s?' % en
    a2 = ['Sí. Atendemos las 24 horas, los 7 días de la semana, también de noche, en fin de semana y en días feriados.',
          'Sí, el servicio es 24/7. A cualquier hora puede llamar al %s.' % TEL,
          'Sí. No cerramos: la atención es las 24 horas todos los días del año.'][v]
    q3 = '¿Qué hago si mi vehículo se queda varado %s?' % en
    a3 = ['Si puede, llévelo a un lugar seguro, encienda las luces intermitentes y evite quedarse dentro si está en una vía rápida. Después llame o escriba por WhatsApp al %s y mande su ubicación.' % TEL,
          'Prioridad uno: su seguridad. Active las intermitentes, salga del vehículo si la vía es transitada y llame al %s. Con su ubicación y el tipo de vehículo enviamos la grúa.' % TEL,
          'Mantenga la calma, encienda las intermitentes y colóquese en un lugar seguro. Luego comuníquese al %s e indíquenos dónde está.' % TEL][v]
    q4 = '¿Pueden mover camiones o maquinaria %s?' % en
    a4 = ['Sí. Además de carros, pick-ups y camionetas trasladamos vehículos pesados y maquinaria industrial. Al llamar, díganos de qué se trata y su peso aproximado para enviar el equipo correcto.',
          'Sí, atendemos remolque liviano y pesado y traslado de maquinaria industrial. Indique el tipo de equipo y su peso aproximado al hacer la llamada.',
          'Sí. Trabajamos con vehículos livianos y pesados, y con maquinaria industrial. Cuéntenos qué necesita mover y nosotros definimos el equipo.'][v]
    return ('<h2>Preguntas frecuentes sobre grúas %s</h2>' % en
            + '<h3>%s</h3><p>%s</p><h3>%s</h3><p>%s</p><h3>%s</h3><p>%s</p><h3>%s</h3><p>%s</p>' % (q1, a1, q2, a2, q3, a3, q4, a4))


def contact_form():
    return {'type': 'form', 'title': 'Envíenos un mensaje',
            'labels': {'name': 'Nombre *', 'phone': 'Teléfono *', 'email': 'Correo electrónico', 'service': 'Tipo de servicio *', 'service_ph': 'Seleccione una opción',
                       'place': 'Ubicación / destino', 'message': 'Mensaje *', 'send': 'Enviar mensaje'},
            'services': ['Remolque de vehículo liviano', 'Remolque de vehículo pesado', 'Transporte de maquinaria industrial', 'Otro servicio'],
            'wa_intro': 'Hola, necesito el servicio de grúas.'}


def hero(h1a, h1b, alt):
    return {'fit': 'cover', 'title': h1a, 'subtitle': h1b, 'text': 'Servicio de Grúas 24/7', 'btn_text': 'Llámenos: {phone}', 'btn_link': 'tel:{tel}', 'image': 0, 'alt': alt}


def muni_page(idx, k):
    _, en_name, full, dept, km, ctx, uso = by_key[k]
    cap = (k == 'guatemala')
    en = 'en ' + en_name
    v = idx % 3
    zonas = ZONAS.get(k)
    # ------- SEO
    T = [
        'Grúas en %s 24 Horas | Grúas Express Towing' % full,
        'Grúas en %s 24/7 | Grúas Express Towing' % full,
        'Grúas en %s | Grúas Express Towing' % full,
        'Grúas en %s 24/7 | Grúas Express' % full,
        'Grúas en %s | Grúas Express' % full,
    ]
    title = title_fit(T)
    D = [
        ['%s. Servicio de grúas %s las 24 horas, todos los días. Remolque liviano y pesado desde Q250. Grúas Express Towing Guatemala.' % (PRE, en),
         '%s. Grúas %s 24/7: remolque liviano, pesado y maquinaria desde Q250. Llámenos o escriba por WhatsApp.' % (PRE, en),
         '%s. Grúas %s, 24 horas. Remolque de vehículos livianos y pesados desde Q250. Grúas Express Towing.' % (PRE, en),
         '%s. Grúas %s 24/7, desde Q250. Remolque liviano y pesado. Grúas Express Towing.' % (PRE, en)],
        ['%s. ¿Necesita una grúa cerca de %s? Atendemos 24/7. Remolque liviano y pesado desde Q250. Grúas Express Towing Guatemala.' % (PRE, en_name if not cap else 'la capital'),
         '%s. Grúa cerca de %s, 24/7. Asistencia vial y remolque desde Q250. Grúas Express Towing.' % (PRE, en_name),
         '%s. Grúa %s 24/7. Remolque desde Q250. Grúas Express Towing.' % (PRE, en)],
        ['%s. Asistencia vial %s con grúa 24 horas. Remolque de carros, camiones y maquinaria desde Q250. Grúas Express Towing.' % (PRE, en),
         '%s. Asistencia vial y grúas %s, 24/7, desde Q250. Grúas Express Towing.' % (PRE, en),
         '%s. Asistencia vial %s, 24/7, desde Q250. Grúas Express.' % (PRE, en)],
    ][v]
    desc = desc_fit(D)
    # ------- cuerpo
    lead = [
        'Si su vehículo se quedó varado %s, llame al %s. Grúas Express Towing atiende %s las 24 horas, los 7 días de la semana.' % (en, TEL, en),
        '¿Necesita una grúa %s? Atendemos a cualquier hora, también de noche y en fin de semana. Teléfono y WhatsApp: %s.' % (en, TEL),
        'Grúas Express Towing presta servicio de remolque %s, de día y de madrugada, desde Q250, con unidades para vehículos livianos y pesados.' % en,
    ][v]
    h2a = ['Grúas %s: remolque liviano y pesado las 24 horas', 'Servicio de grúas %s todos los días', 'Remolque de vehículos %s, a cualquier hora'][v] % en
    how_p = [
        'Cuando necesita una grúa cerca de %s, lo que cuenta es el tiempo. Llame o escriba por WhatsApp al %s y díganos dónde está el vehículo y hacia dónde debe llevarse; nosotros coordinamos el resto.' % (en_name, TEL),
        'Para pedir el servicio basta una llamada o un mensaje de WhatsApp al %s. Con la ubicación en %s y el tipo de vehículo enviamos la grúa.' % (TEL, en_name),
        'No necesita registrarse ni llenar formularios largos: llame o escriba al %s, indique su ubicación %s y el destino, y salimos.' % (TEL, en),
    ][v]
    art1 = ('<h2>%s</h2><p>%s</p><p>%s</p>' % (h2a, ctx, uso)
            + '<h3>Vehículos livianos</h3><p>Carros, pick-ups, camionetas y vehículos que no arrancan, que sufrieron un choque leve o que simplemente no pueden seguir. Los trasladamos con remolque terrestre al taller, a su casa o al destino que indique.</p>'
            + '<h3>Camiones y vehículos pesados</h3><p>También atendemos camiones y otros vehículos pesados, con equipo acorde al tamaño y al peso.</p>'
            + '<h3>Maquinaria industrial</h3><p>Si necesita mover maquinaria, la trasladamos dentro y fuera de %s. Díganos qué equipo es y su peso aproximado.</p>' % (en_name if not cap else 'la capital')
            + '<h3>Asistencia vial</h3><p>La asistencia vial con grúa es nuestro trabajo diario: cuando el vehículo no puede circular, lo recogemos y lo llevamos a un lugar seguro.</p>')
    art2 = ('<h2>Cómo pedir una grúa %s</h2><p>%s</p>' % (en, how_p)
            + '<ol><li>Llame o escriba por WhatsApp al %s.</li><li>Indique dónde está, qué vehículo es y a dónde debe ir.</li><li>Confirme el precio y espere la grúa.</li></ol>' % TEL
            + '<h2>Precio de la grúa %s</h2><p>El servicio de grúas %s inicia en Q250. El valor final depende del vehículo y de la distancia; pregunte al llamar al %s y se lo confirmamos.</p>' % (en, en, TEL))
    blocks = [
        {'type': 'text', 'variant': 'welcome', 'html': '<p><strong>Servicio de grúas %s</strong></p><p>%s</p>' % (en, lead)},
        {'type': 'text', 'variant': 'article', 'html': art1},
        {'type': 'text', 'variant': 'article', 'html': art2},
    ]
    if zonas:
        items = []
        txt = ''
        if k in ZONA_PAGES:
            for z in ZONA_PAGES[k]:
                items.append({'label': 'Grúas en %s' % zone_name(k, z), 'path': zpath(k, z)})
        zl = ', '.join('zona %s' % z for z in zonas[:-1]) + ' y zona %s' % zonas[-1] if k != 'guatemala' else 'las zonas 1 a 19, 21, 24 y 25'
        if k == 'guatemala':
            ztxt = 'Cubrimos todas las zonas de la ciudad: %s. Las zonas 20, 22 y 23 no pertenecen al municipio de Guatemala.' % zl
        elif k in ('mixco', 'villa-nueva'):
            ztxt = 'Damos servicio en todas las zonas: %s.' % ', '.join('zona %s' % z for z in zonas)
        else:
            ztxt = 'Damos servicio en todas las zonas del municipio: %s.' % ', '.join('zona %s' % z for z in zonas)
        blocks.append({'type': 'text', 'variant': 'article', 'html': '<h2>Grúas por zona %s</h2><p>%s Pregunte por la zona exacta al llamar al %s.</p>' % (en, ztxt, TEL)})
        if items:
            blocks.append({'type': 'links', 'title': '', 'items': items})
    blocks.append({'type': 'cards'})
    blocks.append({'type': 'text', 'variant': 'article', 'html': faq(en, v)})
    nb = [{'label': mlabel(x), 'path': mpath(x)} for x in nearest(k)]
    blocks.append({'type': 'links', 'title': 'Otros municipios donde damos servicio de grúas', 'items': nb})
    blocks.append({'type': 'text', 'variant': 'closing', 'html': '<p><strong>Te garantizamos un excelente servicio.</strong></p>'})
    blocks.append(contact_form())
    data = {
        'label': 'Grúas en ' + full, 'path': mpath(k), 'seo_title': title, 'seo_desc': desc, 'seo_page': True,
        'hero': hero('Grúas en %s' % full, 'Servicio 24 horas', 'Grúa de plataforma de Grúas Express Towing en %s' % full),
        'ld_area': full, 'blocks': blocks,
    }
    return {'slug': mpath(k).strip('/'), 'data': data}


def zone_page(k, z):
    _, en_name, full, dept, km, ctx, uso = by_key[k]
    zn = zone_name(k, z)
    zfull = 'zona %s de %s' % (z, 'Guatemala' if k == 'guatemala' else full)
    fact = ZONA_FACT.get((k, z), '')
    T = ['Grúas en Zona %s de %s 24 Horas | Grúas Express' % (z, 'Guatemala' if k == 'guatemala' else full),
         'Grúas en Zona %s de %s 24/7 | Grúas Express' % (z, 'Guatemala' if k == 'guatemala' else full),
         'Grúas Zona %s %s | Grúas Express' % (z, 'Guatemala' if k == 'guatemala' else full)]
    title = title_fit(T)
    D = ['%s. Grúas en %s las 24 horas. Remolque de carros, camiones y maquinaria desde Q250. Grúas Express Towing.' % (PRE, zn),
         '%s. Grúas en %s, 24/7. Remolque liviano y pesado desde Q250. Grúas Express Towing.' % (PRE, zn),
         '%s. Grúa en %s, 24/7, desde Q250. Grúas Express.' % (PRE, zn)]
    desc = desc_fit(D)
    en = 'en la ' + zn
    mp = by_key[k]
    art = ('<h2>Servicio de grúas %s</h2><p>%s</p>' % (en, fact)
           + '<p>Grúas Express Towing atiende la %s las 24 horas, todos los días, con remolque terrestre liviano y pesado. Si su vehículo no arranca, tuvo un percance o necesita trasladarse, llame al %s y díganos dónde está.</p>' % (zn, TEL)
           + '<h2>Cómo pedir una grúa %s</h2><ol><li>Llame o escriba por WhatsApp al %s.</li><li>Indique la dirección o un punto de referencia en la zona %s.</li><li>Confirme el precio, que parte de Q250, y espere la grúa.</li></ol>' % (en, TEL, z)
           + '<h2>Precio de la grúa %s</h2><p>El servicio inicia en Q250. El valor final depende del vehículo y del destino; se lo confirmamos al llamar.</p>' % en)
    zl = [{'label': 'Grúas en %s' % zone_name(k, zz), 'path': zpath(k, zz)} for zz in ZONA_PAGES[k] if zz != z]
    blocks = [
        {'type': 'text', 'variant': 'welcome', 'html': '<p><strong>Grúas %s</strong></p><p>¿Necesita una grúa %s? Llame al %s: atendemos a cualquier hora, todos los días.</p>' % (en, en, TEL)},
        {'type': 'text', 'variant': 'article', 'html': art},
        {'type': 'cards'},
        {'type': 'text', 'variant': 'article', 'html': faq(en, int(z) % 3)},
        {'type': 'links', 'title': 'Más zonas y el municipio completo', 'items': [{'label': mlabel(k), 'path': mpath(k)}] + zl},
        {'type': 'text', 'variant': 'closing', 'html': '<p><strong>Te garantizamos un excelente servicio.</strong></p>'},
        contact_form(),
    ]
    data = {'label': 'Grúas en ' + zfull, 'path': zpath(k, z), 'seo_title': title, 'seo_desc': desc, 'seo_page': True,
            'hero': hero('Grúas en %s' % zfull.replace('zona', 'zona'), 'Servicio 24 horas', 'Grúa de plataforma de Grúas Express Towing en la %s' % zfull),
            'ld_area': zfull[0].upper() + zfull[1:], 'blocks': blocks}
    return {'slug': zpath(k, z).strip('/'), 'data': data}


pages = []
for i, k in enumerate(order):
    pages.append(muni_page(i, k))
for k, zs in ZONA_PAGES.items():
    for z in zs:
        pages.append(zone_page(k, z))

existing = {
    'inicio': {'seo_title': 'Grúas en Guatemala 24 Horas | Grúas Express Towing',
               'seo_desc': PRE + '. Servicio de grúas 24/7 en toda la República de Guatemala. Remolque liviano y pesado, asistencia vial y maquinaria desde Q250.'},
    'sobre-nosotros': {'seo_title': 'Sobre Nosotros | Grúas Express Towing Guatemala',
                       'seo_desc': PRE + '. Empresa guatemalteca de servicio de grúas con remolque terrestre liviano y pesado las 24 horas, los 7 días de la semana.'},
    'servicio-de-gruas-24-7': {'seo_title': 'Servicio de Grúas 24/7 en Guatemala | Grúas Express',
                               'seo_desc': PRE + '. Servicio de grúas 24 horas en Guatemala: asistencia vial, remolque de vehículos livianos y pesados y maquinaria. Desde Q250.'},
    'contactenos': {'seo_title': 'Contacto y WhatsApp 3556-7904 | Grúas Express',
                    'seo_desc': PRE + '. Llámenos o escriba por WhatsApp para pedir una grúa en Guatemala. Email: info@gruasexpresstowing.gt. También puede enviarnos un mensaje.'},
}
for e in existing.values():
    assert len(e['seo_title']) <= 60, e['seo_title']
    assert len(e['seo_desc']) <= 160 and e['seo_desc'].startswith(PRE), (len(e['seo_desc']), e['seo_desc'])

coverage = {'type': 'links', 'title': 'Municipios que atendemos', 'items': [{'label': mlabel(k), 'path': mpath(k)} for k in order]}
out = {'version': 1, 'existing': existing, 'coverage': coverage, 'pages': pages}
dst = os.path.join(ROOT, 'site/_core/seed/geo.json')
json.dump(out, open(dst, 'w'), ensure_ascii=False, indent=1)
print('páginas SEO:', len(pages), '->', dst)
