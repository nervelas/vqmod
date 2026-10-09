<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * Datos de redacción base por rubro (CONTRATO-LUXE §2), SIN IA.
 *
 * Solo contenido persuasivo genérico del rubro: nada de cifras, años,
 * certificaciones, premios, direcciones, teléfonos, testimonios ni precios.
 * Las respuestas de preguntas frecuentes remiten al contacto/horario del cliente.
 *
 * Estructura por rubro (es):
 *   label, lead (sprintf %s = negocio), cita, valores[5]{titulo,texto,icono},
 *   proceso[4]{titulo,texto}, faq1{p,r}, cat[8]{nombre,resumen,descripcion,icono}
 * Estructura por rubro (en): label, lead, cita, cat[8]{nombre,resumen}
 */
final class BaseTextsData
{
    /** @return array<string,array> */
    public static function es(): array
    {
        return [
            'abogado' => [
                'label' => 'Servicios legales',
                'lead' => '%s: orientación legal clara, con trato cercano y reserva en cada caso.',
                'cita' => 'Cada caso merece atención, claridad y un camino bien trazado.',
                'valores' => [
                    ['Orientación clara', 'Le explicamos cada opción en palabras sencillas, sin tecnicismos innecesarios.', 'book'],
                    ['Confidencialidad', 'Su información se maneja con reserva y respeto en todo momento.', 'shield'],
                    ['Atención personalizada', 'Cada asunto se estudia con detalle y se atiende de forma individual.', 'handshake'],
                    ['Rigor profesional', 'Trabajamos con orden, método y atención a los plazos de cada trámite.', 'scales'],
                    ['Comunicación constante', 'Le mantenemos informado sobre el avance de su asunto.', 'chat'],
                ],
                'proceso' => [
                    ['Consulta inicial', 'Nos cuenta su situación y revisamos los documentos que tenga a la mano.'],
                    ['Análisis del caso', 'Estudiamos su asunto y le explicamos las alternativas posibles.'],
                    ['Estrategia', 'Definimos junto con usted los pasos a seguir.'],
                    ['Acompañamiento', 'Le acompañamos e informamos del avance hasta concluir.'],
                ],
                'faq1' => ['¿Cómo puedo plantear mi caso?', 'Puede escribirnos por WhatsApp, llamarnos o usar el formulario de contacto de esta página. Con gusto le indicamos cómo continuar.'],
                'cat' => [
                    ['Asesoría legal', 'Orientación para que usted tome decisiones con información clara.', 'Le orientamos sobre sus derechos y obligaciones y le explicamos las opciones disponibles para su situación, con lenguaje sencillo y trato respetuoso.', 'scales'],
                    ['Derecho civil', 'Apoyo en asuntos civiles de personas y familias.', 'Le acompañamos en asuntos civiles como obligaciones, propiedad y sucesiones, revisando su situación y explicando cada paso a seguir.', 'columns'],
                    ['Derecho laboral', 'Orientación para trabajadores y empleadores.', 'Atendemos consultas sobre relaciones de trabajo, contratos y prestaciones, buscando soluciones claras para ambas partes.', 'handshake'],
                    ['Derecho mercantil', 'Respaldo legal para comercios y empresas.', 'Apoyamos a comerciantes y empresas en asuntos mercantiles, desde la forma de organizar su negocio hasta la revisión de sus acuerdos.', 'briefcase'],
                    ['Derecho de familia', 'Acompañamiento respetuoso en asuntos familiares.', 'Atendemos con sensibilidad asuntos familiares, explicando con calma los procedimientos y las alternativas para cada caso.', 'heart'],
                    ['Contratos y documentos', 'Redacción y revisión de documentos legales.', 'Elaboramos y revisamos contratos y documentos para que reflejen con claridad lo acordado y protejan sus intereses.', 'contract'],
                    ['Asesoría a empresas', 'Acompañamiento legal para el día a día de su empresa.', 'Brindamos apoyo legal continuo a empresas, ayudándole a prevenir problemas y a tomar decisiones con respaldo.', 'building'],
                    ['Trámites y gestiones', 'Apoyo en trámites ante instituciones.', 'Le ayudamos a preparar y dar seguimiento a trámites y gestiones, para que usted ahorre tiempo y evite errores.', 'document'],
                ],
            ],
            'clinica' => [
                'label' => 'Salud y bienestar',
                'lead' => '%s: atención de salud cercana, respetuosa y centrada en usted.',
                'cita' => 'Su salud en manos que escuchan, explican y acompañan.',
                'valores' => [
                    ['Trato humano', 'Le atendemos con calidez, paciencia y respeto por su tiempo.', 'heart'],
                    ['Explicaciones claras', 'Le decimos con sencillez qué encontramos y qué opciones existen.', 'stethoscope'],
                    ['Atención personalizada', 'Cada persona es distinta; su atención también.', 'users'],
                    ['Ambiente de confianza', 'Un espacio ordenado y tranquilo para consultar sin prisas.', 'shield'],
                    ['Seguimiento', 'Nos interesa cómo evoluciona usted después de la consulta.', 'pulse'],
                ],
                'proceso' => [
                    ['Agende su cita', 'Escríbanos o llámenos y coordinamos el momento que le convenga.'],
                    ['Evaluación', 'Escuchamos sus molestias y revisamos su caso con detenimiento.'],
                    ['Plan de atención', 'Le explicamos las opciones y acordamos el camino a seguir.'],
                    ['Seguimiento', 'Damos seguimiento a su evolución y resolvemos sus dudas.'],
                ],
                'faq1' => ['¿Cómo agendo una cita?', 'Puede escribirnos por WhatsApp, llamarnos o usar el formulario de esta página. Le indicaremos la disponibilidad dentro del horario de atención publicado.'],
                'cat' => [
                    ['Consulta general', 'Evaluación de salud con atención personalizada.', 'Revisamos su situación, escuchamos sus molestias y le orientamos sobre los siguientes pasos, con explicaciones claras y trato cercano.', 'stethoscope'],
                    ['Control y prevención', 'Seguimiento para cuidar su salud a tiempo.', 'Le acompañamos con controles periódicos y recomendaciones para cuidar su bienestar de forma preventiva.', 'pulse'],
                    ['Salud familiar', 'Atención pensada para toda la familia.', 'Atendemos las necesidades de salud de distintas edades en un ambiente cálido, donde cada integrante se siente escuchado.', 'users'],
                    ['Salud dental', 'Cuidado de su sonrisa y su salud bucal.', 'Le orientamos sobre el cuidado de sus dientes y encías, con explicaciones claras y trato amable.', 'tooth'],
                    ['Atención pediátrica', 'Cuidado atento para niñas y niños.', 'Ofrecemos una atención paciente y amable para los más pequeños, acompañando también a sus familias.', 'baby'],
                    ['Laboratorio y estudios', 'Apoyo con estudios para su diagnóstico.', 'Le orientamos sobre los estudios que pueda necesitar y le explicamos cómo prepararse y qué esperar.', 'microscope'],
                    ['Orientación nutricional', 'Hábitos de alimentación acordes a usted.', 'Le ayudamos a entender su alimentación y a construir hábitos que se ajusten a su estilo de vida.', 'leaf'],
                    ['Cuidado de la vista', 'Atención para cuidar su salud visual.', 'Revisamos su salud visual y le orientamos para cuidar sus ojos con tranquilidad y confianza.', 'eye'],
                ],
            ],
            'taller' => [
                'label' => 'Servicio automotriz',
                'lead' => '%s: servicio automotriz claro, con explicaciones honestas y trabajo bien hecho.',
                'cita' => 'Un vehículo bien atendido le da tranquilidad en cada trayecto.',
                'valores' => [
                    ['Diagnóstico claro', 'Le explicamos qué necesita su vehículo y por qué, sin rodeos.', 'wrench'],
                    ['Trabajo ordenado', 'Cada servicio se realiza con método y atención al detalle.', 'gear'],
                    ['Trato honesto', 'Le informamos antes de realizar cualquier trabajo.', 'shield'],
                    ['Repuestos adecuados', 'Le orientamos para elegir lo que conviene a su vehículo.', 'battery'],
                    ['Atención cercana', 'Resolvemos sus dudas con paciencia y lenguaje sencillo.', 'chat'],
                ],
                'proceso' => [
                    ['Recepción', 'Nos cuenta qué le sucede a su vehículo y lo revisamos.'],
                    ['Diagnóstico', 'Identificamos el problema y le explicamos las opciones.'],
                    ['Cotización', 'Le informamos el trabajo a realizar antes de comenzar.'],
                    ['Entrega', 'Le entregamos su vehículo y le explicamos lo realizado.'],
                ],
                'faq1' => ['¿Cómo solicito una cotización?', 'Escríbanos por WhatsApp, llámenos o use el formulario de esta página y cuéntenos qué necesita su vehículo. Con esa información le damos seguimiento.'],
                'cat' => [
                    ['Mantenimiento preventivo', 'Revisiones para evitar problemas mayores.', 'Revisamos los puntos clave de su vehículo y le recomendamos el mantenimiento adecuado para que rinda mejor y le dure más.', 'oil'],
                    ['Cambio de aceite y filtros', 'Servicio básico para el buen estado del motor.', 'Realizamos el cambio de aceite y filtros y revisamos otros puntos importantes mientras su vehículo está con nosotros.', 'droplet'],
                    ['Frenos', 'Revisión y reparación del sistema de frenos.', 'Revisamos el sistema de frenos y le explicamos con claridad qué conviene reparar o reemplazar para su seguridad.', 'shield'],
                    ['Suspensión y dirección', 'Comodidad y control al conducir.', 'Evaluamos el estado de la suspensión y la dirección para que su vehículo responda con seguridad y comodidad.', 'gear'],
                    ['Sistema eléctrico', 'Diagnóstico de batería y componentes eléctricos.', 'Revisamos la batería y los componentes eléctricos para encontrar el origen de fallas y evitar sorpresas.', 'battery'],
                    ['Llantas y alineación', 'Cuidado de llantas y estabilidad.', 'Revisamos el estado de sus llantas y le orientamos sobre rotación, balanceo y alineación.', 'tire'],
                    ['Diagnóstico de motor', 'Identificamos la causa de fallas del motor.', 'Hacemos una evaluación ordenada del motor para identificar fallas y proponerle soluciones claras.', 'wrench'],
                    ['Aire acondicionado', 'Revisión del sistema de climatización.', 'Revisamos el sistema de aire acondicionado de su vehículo para que viaje con comodidad.', 'bolt'],
                ],
            ],
            'ropa' => [
                'label' => 'Moda y estilo',
                'lead' => '%s: prendas con estilo para quienes cuidan cómo se ven y cómo se sienten.',
                'cita' => 'Vestir bien es una forma sencilla de sentirse como uno mismo.',
                'valores' => [
                    ['Estilo con personalidad', 'Piezas pensadas para que usted se vea y se sienta bien.', 'shirt'],
                    ['Selección cuidada', 'Elegimos con atención lo que ofrecemos a nuestros clientes.', 'gem'],
                    ['Atención personalizada', 'Le ayudamos a encontrar lo que va con usted y con la ocasión.', 'sparkles'],
                    ['Detalles que cuentan', 'Cuidamos los acabados y la presentación de cada pieza.', 'scissors'],
                    ['Comodidad y ajuste', 'Le orientamos para elegir la prenda y la talla que le queda mejor.', 'ruler'],
                ],
                'proceso' => [
                    ['Descubra', 'Explore nuestras prendas y encuentre lo que le inspira.'],
                    ['Consulte', 'Escríbanos para resolver dudas sobre disponibilidad y tallas.'],
                    ['Elija', 'Le ayudamos a decidir lo que mejor va con usted.'],
                    ['Estrene', 'Coordinamos la entrega para que lo disfrute cuanto antes.'],
                ],
                'faq1' => ['¿Cómo hago una consulta o un pedido?', 'Escríbanos por WhatsApp o use el formulario de esta página y cuéntenos qué prenda le interesa. Le atenderemos con gusto.'],
                'cat' => [
                    ['Ropa para dama', 'Prendas pensadas para distintas ocasiones.', 'Una selección de prendas para dama pensada para el día a día y para ocasiones especiales, con atención a los detalles.', 'shirt'],
                    ['Ropa para caballero', 'Opciones cómodas y con buen corte.', 'Prendas para caballero que combinan comodidad y estilo, para el trabajo, el tiempo libre o una ocasión especial.', 'hanger'],
                    ['Ropa casual', 'Comodidad para el día a día.', 'Piezas cómodas y versátiles para combinar a su gusto en el uso diario.', 'sparkles'],
                    ['Ropa de ocasión', 'Piezas para momentos especiales.', 'Le ayudamos a encontrar la prenda adecuada para una celebración o un evento importante.', 'gem'],
                    ['Accesorios', 'Complementos para completar su look.', 'Complementos que dan el toque final a su atuendo y permiten renovar su estilo con detalles.', 'bag'],
                    ['Calzado', 'Estilo y comodidad en cada paso.', 'Opciones de calzado para acompañar sus looks, con atención a la comodidad.', 'tag'],
                    ['Asesoría de estilo', 'Orientación para combinar sus prendas.', 'Le orientamos para combinar prendas y accesorios de acuerdo con su estilo y la ocasión.', 'sparkles'],
                    ['Ajustes y arreglos', 'Para que cada prenda le quede bien.', 'Consúltenos sobre ajustes y arreglos para que sus prendas le queden como usted desea.', 'scissors'],
                ],
            ],
            'restaurante' => [
                'label' => 'Cocina y hospitalidad',
                'lead' => '%s: sabor, ambiente y atención pensados para que disfrute cada visita.',
                'cita' => 'Buena mesa, buena compañía y un lugar al que da gusto volver.',
                'valores' => [
                    ['Sabor que se nota', 'Cocinamos con dedicación para que cada plato valga la visita.', 'utensils'],
                    ['Ingredientes frescos', 'Cuidamos la calidad y el punto de cada preparación.', 'leaf'],
                    ['Ambiente acogedor', 'Un lugar cómodo para compartir con familia y amigos.', 'coffee'],
                    ['Atención cálida', 'Le recibimos con amabilidad desde que llega.', 'heart'],
                    ['Para toda ocasión', 'Desde una comida sencilla hasta una celebración especial.', 'cake'],
                ],
                'proceso' => [
                    ['Elija', 'Conozca nuestra propuesta y escoja lo que más se le antoje.'],
                    ['Reserve o pida', 'Escríbanos para reservar, consultar o hacer su pedido.'],
                    ['Disfrute', 'Siéntese, relájese y deje que nos ocupemos de todo.'],
                    ['Vuelva', 'Nos encantará recibirle de nuevo.'],
                ],
                'faq1' => ['¿Cómo puedo reservar o hacer un pedido?', 'Escríbanos por WhatsApp, llámenos o use el formulario de esta página. Allí también encontrará nuestro horario de atención.'],
                'cat' => [
                    ['Desayunos', 'Para comenzar el día con buen sabor.', 'Una propuesta de desayunos para empezar el día con energía, en un ambiente tranquilo y acogedor.', 'coffee'],
                    ['Almuerzos', 'Platillos para disfrutar al mediodía.', 'Platillos preparados con dedicación para disfrutar su almuerzo con calma y buen sabor.', 'utensils'],
                    ['Cenas', 'Una mesa agradable para cerrar el día.', 'Un ambiente cómodo para compartir una buena cena con las personas que más aprecia.', 'wine'],
                    ['Platillos de la casa', 'Recetas que nos distinguen.', 'Preparaciones propias de la casa, pensadas para quienes disfrutan de la buena cocina.', 'chef'],
                    ['Postres', 'El toque dulce para despedir la comida.', 'Postres para completar su visita con un final dulce y memorable.', 'cake'],
                    ['Bebidas', 'Para acompañar cada platillo.', 'Una variedad de bebidas para acompañar su comida o disfrutar en cualquier momento.', 'wine'],
                    ['Eventos y celebraciones', 'Reuniones especiales a su medida.', 'Consúltenos para organizar celebraciones y reuniones, y le orientamos según sus necesidades.', 'flame'],
                    ['Pedidos para llevar', 'Nuestro sabor, donde usted lo necesite.', 'Haga su pedido para llevar y disfrute nuestras preparaciones donde más le convenga.', 'bag'],
                ],
            ],
            'transporte' => [
                'label' => 'Transporte y logística',
                'lead' => '%s: traslados y entregas con organización, comunicación y compromiso.',
                'cita' => 'Cada carga llega mejor cuando alguien la cuida de principio a fin.',
                'valores' => [
                    ['Compromiso con su carga', 'Tratamos cada envío como si fuera nuestro.', 'box'],
                    ['Comunicación directa', 'Le mantenemos informado durante el servicio.', 'chat'],
                    ['Rutas bien planificadas', 'Organizamos cada traslado para aprovechar mejor el tiempo.', 'route'],
                    ['Puntualidad', 'Respetamos los tiempos acordados con usted.', 'clock'],
                    ['Seguridad', 'Cuidamos la mercancía y a las personas durante el trayecto.', 'shield'],
                ],
                'proceso' => [
                    ['Cuéntenos su necesidad', 'Indíquenos qué desea trasladar, desde dónde y hacia dónde.'],
                    ['Cotización', 'Le presentamos una propuesta según su necesidad.'],
                    ['Coordinación', 'Acordamos horarios y detalles del servicio.'],
                    ['Entrega', 'Realizamos el traslado y confirmamos la entrega.'],
                ],
                'faq1' => ['¿Cómo solicito una cotización?', 'Escríbanos por WhatsApp, llámenos o use el formulario de esta página e indíquenos qué necesita trasladar. Le responderemos dentro de nuestro horario de atención.'],
                'cat' => [
                    ['Transporte de carga', 'Traslado de mercancía según su necesidad.', 'Trasladamos su mercancía con organización y cuidado, coordinando con usted los detalles del servicio.', 'truck'],
                    ['Entregas a domicilio', 'Su pedido, directo a la puerta.', 'Coordinamos entregas para que sus productos lleguen al lugar y en el momento acordado.', 'box'],
                    ['Fletes', 'Servicio de flete para distintos volúmenes.', 'Ofrecemos servicio de flete adaptado al tipo y volumen de lo que necesita mover.', 'route'],
                    ['Mudanzas', 'Traslado cuidadoso de hogares y oficinas.', 'Le ayudamos a trasladar su hogar u oficina con orden, cuidado y buena comunicación.', 'home'],
                    ['Logística de distribución', 'Organización de rutas y entregas.', 'Le apoyamos a organizar la distribución de sus productos para que lleguen a sus clientes.', 'compass'],
                    ['Servicio empresarial', 'Transporte para las necesidades de su empresa.', 'Atendemos necesidades de transporte de empresas con coordinación previa y comunicación constante.', 'briefcase'],
                    ['Transporte de personal', 'Traslados organizados para grupos.', 'Coordinamos traslados de grupos con puntualidad y atención al confort.', 'users'],
                    ['Envíos especiales', 'Atención a cargas que requieren cuidado.', 'Consúltenos por envíos que requieren atención particular y le indicaremos cómo atenderlos.', 'shield'],
                ],
            ],
            'contabilidad' => [
                'label' => 'Contabilidad y finanzas',
                'lead' => '%s: orden contable y claridad financiera para que usted se enfoque en su negocio.',
                'cita' => 'Los números en orden dan tranquilidad para decidir mejor.',
                'valores' => [
                    ['Orden y precisión', 'Trabajamos con método para que su información sea confiable.', 'calculator'],
                    ['Claridad', 'Le explicamos sus números en palabras comprensibles.', 'chart'],
                    ['Confidencialidad', 'La información de su negocio se maneja con reserva.', 'shield'],
                    ['Cumplimiento a tiempo', 'Le recordamos y coordinamos sus obligaciones con anticipación.', 'calendar'],
                    ['Acompañamiento cercano', 'Estamos disponibles para resolver sus dudas.', 'handshake'],
                ],
                'proceso' => [
                    ['Conversamos', 'Conocemos su negocio y lo que necesita ordenar.'],
                    ['Revisión', 'Analizamos su información contable y sus obligaciones.'],
                    ['Propuesta', 'Le planteamos cómo podemos apoyarle.'],
                    ['Seguimiento', 'Le mantenemos informado de forma periódica.'],
                ],
                'faq1' => ['¿Cómo solicito asesoría?', 'Escríbanos por WhatsApp, llámenos o use el formulario de esta página y cuéntenos qué necesita. Le indicaremos cómo continuar.'],
                'cat' => [
                    ['Contabilidad general', 'Registro ordenado de la actividad de su negocio.', 'Llevamos el registro ordenado de las operaciones de su negocio para que cuente con información clara y al día.', 'calculator'],
                    ['Asesoría tributaria', 'Orientación para cumplir con sus obligaciones.', 'Le orientamos sobre sus obligaciones tributarias y le ayudamos a cumplirlas de forma ordenada.', 'percent'],
                    ['Declaraciones y trámites', 'Preparación y seguimiento de trámites.', 'Preparamos y damos seguimiento a declaraciones y trámites, con atención a los plazos que corresponden.', 'document'],
                    ['Planilla y nómina', 'Apoyo en el cálculo y control de planillas.', 'Le apoyamos en el cálculo y control de la planilla de su personal con orden y confidencialidad.', 'users'],
                    ['Estados financieros', 'Información clara para decidir.', 'Preparamos estados financieros que le ayudan a entender la situación de su negocio.', 'chart'],
                    ['Facturación', 'Apoyo con su facturación y documentos.', 'Le ayudamos a organizar su facturación y el archivo de sus documentos.', 'receipt'],
                    ['Constitución de empresas', 'Orientación para formalizar su negocio.', 'Le orientamos en los pasos para formalizar su negocio y comenzar de forma ordenada.', 'building'],
                    ['Consultoría financiera', 'Apoyo para planificar y decidir.', 'Conversamos con usted sobre la salud financiera de su negocio y le planteamos opciones para mejorarla.', 'coins'],
                ],
            ],
            'importaciones' => [
                'label' => 'Importaciones y comercio',
                'lead' => '%s: apoyo para traer y comercializar productos con orden y comunicación clara.',
                'cita' => 'Un buen proceso de importación empieza con claridad y buena coordinación.',
                'valores' => [
                    ['Comunicación clara', 'Le explicamos cada etapa del proceso de forma sencilla.', 'chat'],
                    ['Coordinación ordenada', 'Organizamos los pasos para evitar contratiempos.', 'container'],
                    ['Cuidado de su pedido', 'Damos seguimiento atento a lo que usted necesita traer.', 'box'],
                    ['Visión de negocio', 'Entendemos que cada producto cuenta para su negocio.', 'globe'],
                    ['Trato cercano', 'Un contacto directo para resolver sus dudas.', 'handshake'],
                ],
                'proceso' => [
                    ['Su necesidad', 'Nos cuenta qué desea importar o comercializar.'],
                    ['Cotización', 'Le presentamos una propuesta según su pedido.'],
                    ['Coordinación', 'Organizamos los pasos y le informamos del avance.'],
                    ['Entrega', 'Recibe su pedido y resolvemos cualquier duda.'],
                ],
                'faq1' => ['¿Cómo solicito una cotización?', 'Escríbanos por WhatsApp, llámenos o use el formulario de esta página e indíquenos qué desea importar. Le responderemos dentro de nuestro horario.'],
                'cat' => [
                    ['Importación de productos', 'Apoyo para traer lo que su negocio necesita.', 'Le acompañamos en la coordinación para traer los productos que necesita, manteniéndole informado en cada etapa.', 'globe'],
                    ['Comercialización', 'Productos disponibles para su negocio.', 'Consúltenos por los productos que comercializamos y le orientamos según lo que necesite.', 'tag'],
                    ['Gestión de pedidos', 'Seguimiento atento de cada pedido.', 'Damos seguimiento a su pedido para que usted sepa en qué etapa se encuentra.', 'barcode'],
                    ['Transporte internacional', 'Coordinación del traslado de su carga.', 'Coordinamos el traslado de su mercancía y le explicamos las opciones disponibles.', 'ship'],
                    ['Trámites aduanales', 'Orientación en gestiones de importación.', 'Le orientamos en los trámites necesarios para que su mercancía avance con orden.', 'document'],
                    ['Almacenaje', 'Resguardo de mercancía.', 'Consúltenos por opciones para resguardar su mercancía mientras la distribuye.', 'warehouse'],
                    ['Distribución local', 'Entrega de producto a sus clientes.', 'Le apoyamos con la distribución de producto hacia sus puntos de venta.', 'truck'],
                    ['Asesoría de comercio', 'Orientación para decidir mejor.', 'Conversamos con usted sobre sus necesidades de abastecimiento y le planteamos opciones.', 'container'],
                ],
            ],
            'otro' => [
                'label' => 'Atención personalizada',
                'lead' => '%s: un servicio cercano, cuidado y pensado para lo que usted necesita.',
                'cita' => 'Lo bien hecho se nota en los detalles y en el trato.',
                'valores' => [
                    ['Atención personalizada', 'Escuchamos lo que usted necesita antes de proponer.', 'users'],
                    ['Trato cercano', 'Le atendemos con amabilidad, respeto y claridad.', 'handshake'],
                    ['Trabajo cuidadoso', 'Ponemos atención en los detalles de cada servicio.', 'sparkles'],
                    ['Comunicación clara', 'Le explicamos cada paso con palabras sencillas.', 'chat'],
                    ['Compromiso', 'Nos importa que usted quede conforme con lo que recibe.', 'check'],
                ],
                'proceso' => [
                    ['Conversamos', 'Nos cuenta lo que necesita y resolvemos sus dudas.'],
                    ['Propuesta', 'Le planteamos cómo podemos ayudarle.'],
                    ['Manos a la obra', 'Realizamos el trabajo con atención y orden.'],
                    ['Seguimiento', 'Nos aseguramos de que usted quede conforme.'],
                ],
                'faq1' => ['¿Cómo puedo contactarlos?', 'Escríbanos por WhatsApp, llámenos o use el formulario de esta página. Los datos de contacto y el horario de atención están en la sección de contacto.'],
                'cat' => [
                    ['Atención personalizada', 'Un servicio pensado según su necesidad.', 'Escuchamos lo que usted necesita y le proponemos la mejor manera de atenderlo, con trato cercano.', 'users'],
                    ['Asesoría y orientación', 'Le ayudamos a decidir con claridad.', 'Conversamos con usted para entender su situación y orientarle con información clara.', 'lightbulb'],
                    ['Servicio a domicilio', 'Atención donde usted lo necesite.', 'Consúltenos por la posibilidad de atenderle en el lugar que mejor le convenga.', 'home'],
                    ['Servicio para empresas', 'Apoyo para las necesidades de su empresa.', 'Atendemos necesidades de empresas con coordinación previa y comunicación constante.', 'briefcase'],
                    ['Servicio a la medida', 'Soluciones adaptadas a su caso.', 'Cuando su necesidad es particular, conversamos y adaptamos el servicio a lo que usted requiere.', 'target'],
                    ['Mantenimiento y seguimiento', 'Acompañamiento después del servicio.', 'Damos seguimiento para asegurarnos de que el resultado le deje conforme.', 'check'],
                    ['Consultas y cotizaciones', 'Resuelva sus dudas antes de decidir.', 'Escríbanos para resolver sus dudas y conocer cómo podemos ayudarle.', 'chat'],
                    ['Atención especial', 'Para necesidades particulares.', 'Si lo que busca no aparece en la lista, consúltenos y vemos cómo atenderle.', 'star'],
                ],
            ],
        ];
    }

    /** @return array<string,array> */
    public static function en(): array
    {
        return [
            'abogado' => [
                'label' => 'Legal services', 'lead' => '%s: clear legal guidance with a personal touch and discretion in every matter.',
                'cita' => 'Every matter deserves attention, clarity and a well-planned path.',
                'cat' => [
                    ['Legal advice', 'Guidance so you can decide with clear information.'], ['Civil law', 'Support with civil matters for people and families.'],
                    ['Labor law', 'Guidance for employees and employers.'], ['Commercial law', 'Legal support for businesses.'],
                    ['Family law', 'Respectful support in family matters.'], ['Contracts and documents', 'Drafting and review of legal documents.'],
                    ['Business advisory', 'Day-to-day legal support for your company.'], ['Procedures and filings', 'Help with paperwork before institutions.'],
                ],
            ],
            'clinica' => [
                'label' => 'Health and wellness', 'lead' => '%s: close, respectful health care centered on you.',
                'cita' => 'Your health in hands that listen, explain and support.',
                'cat' => [
                    ['General consultation', 'Health evaluation with personal attention.'], ['Checkups and prevention', 'Follow-up to look after your health in time.'],
                    ['Family health', 'Care designed for the whole family.'], ['Dental health', 'Care for your smile and oral health.'],
                    ['Pediatric care', 'Attentive care for children.'], ['Lab and studies', 'Support with studies for your diagnosis.'],
                    ['Nutritional guidance', 'Eating habits that fit you.'], ['Vision care', 'Care for your eye health.'],
                ],
            ],
            'taller' => [
                'label' => 'Auto service', 'lead' => '%s: clear auto service with honest explanations and well-done work.',
                'cita' => 'A well-kept vehicle gives you peace of mind on every trip.',
                'cat' => [
                    ['Preventive maintenance', 'Checks to avoid bigger problems.'], ['Oil and filter change', 'Basic service for a healthy engine.'],
                    ['Brakes', 'Brake system inspection and repair.'], ['Suspension and steering', 'Comfort and control while driving.'],
                    ['Electrical system', 'Battery and electrical diagnostics.'], ['Tires and alignment', 'Tire care and stability.'],
                    ['Engine diagnostics', 'We identify the cause of engine faults.'], ['Air conditioning', 'Climate system inspection.'],
                ],
            ],
            'ropa' => [
                'label' => 'Fashion and style', 'lead' => '%s: stylish pieces for those who care how they look and feel.',
                'cita' => 'Dressing well is a simple way of feeling like yourself.',
                'cat' => [
                    ['Womenswear', 'Pieces for different occasions.'], ['Menswear', 'Comfortable options with a good cut.'],
                    ['Casual wear', 'Everyday comfort.'], ['Occasion wear', 'Pieces for special moments.'],
                    ['Accessories', 'Details to complete your look.'], ['Footwear', 'Style and comfort in every step.'],
                    ['Style advice', 'Guidance to combine your pieces.'], ['Alterations', 'So every piece fits you well.'],
                ],
            ],
            'restaurante' => [
                'label' => 'Cuisine and hospitality', 'lead' => '%s: flavor, atmosphere and service designed for you to enjoy every visit.',
                'cita' => 'Good food, good company and a place worth returning to.',
                'cat' => [
                    ['Breakfast', 'A tasty way to start the day.'], ['Lunch', 'Dishes to enjoy at midday.'],
                    ['Dinner', 'A pleasant table to close the day.'], ['House specialties', 'Recipes that set us apart.'],
                    ['Desserts', 'A sweet touch to finish your meal.'], ['Drinks', 'To go with every dish.'],
                    ['Events and celebrations', 'Special gatherings tailored to you.'], ['Takeaway orders', 'Our flavor, wherever you need it.'],
                ],
            ],
            'transporte' => [
                'label' => 'Transport and logistics', 'lead' => '%s: transfers and deliveries with organization, communication and commitment.',
                'cita' => 'Every load arrives better when someone cares for it from start to finish.',
                'cat' => [
                    ['Freight transport', 'Moving goods according to your needs.'], ['Home delivery', 'Your order, straight to the door.'],
                    ['Hauling', 'Hauling service for different volumes.'], ['Moving', 'Careful relocation of homes and offices.'],
                    ['Distribution logistics', 'Route and delivery planning.'], ['Business service', 'Transport for your company needs.'],
                    ['Staff transport', 'Organized transfers for groups.'], ['Special shipments', 'Attention for loads that need care.'],
                ],
            ],
            'contabilidad' => [
                'label' => 'Accounting and finance', 'lead' => '%s: accounting order and financial clarity so you can focus on your business.',
                'cita' => 'Numbers in order give you peace of mind to decide better.',
                'cat' => [
                    ['General accounting', 'Organized records of your business activity.'], ['Tax advisory', 'Guidance to meet your obligations.'],
                    ['Filings and procedures', 'Preparation and follow-up of filings.'], ['Payroll', 'Support with payroll calculation and control.'],
                    ['Financial statements', 'Clear information to decide.'], ['Invoicing', 'Support with invoicing and documents.'],
                    ['Company setup', 'Guidance to formalize your business.'], ['Financial consulting', 'Support to plan and decide.'],
                ],
            ],
            'importaciones' => [
                'label' => 'Imports and trade', 'lead' => '%s: support to bring in and sell products with order and clear communication.',
                'cita' => 'A good import process starts with clarity and good coordination.',
                'cat' => [
                    ['Product imports', 'Support to bring in what your business needs.'], ['Product sales', 'Products available for your business.'],
                    ['Order management', 'Attentive follow-up of every order.'], ['International transport', 'Coordination of your cargo transfer.'],
                    ['Customs procedures', 'Guidance on import paperwork.'], ['Storage', 'Safekeeping of goods.'],
                    ['Local distribution', 'Delivering product to your customers.'], ['Trade advisory', 'Guidance to decide better.'],
                ],
            ],
            'otro' => [
                'label' => 'Personal attention', 'lead' => '%s: a close, careful service designed around what you need.',
                'cita' => 'Good work shows in the details and in the way people are treated.',
                'cat' => [
                    ['Personal attention', 'A service designed around your needs.'], ['Advice and guidance', 'We help you decide with clarity.'],
                    ['On-site service', 'Service wherever you need it.'], ['Business service', 'Support for your company needs.'],
                    ['Tailored service', 'Solutions adapted to your case.'], ['Maintenance and follow-up', 'Support after the service.'],
                    ['Questions and quotes', 'Clear your doubts before deciding.'], ['Special requests', 'For particular needs.'],
                ],
            ],
        ];
    }
}
