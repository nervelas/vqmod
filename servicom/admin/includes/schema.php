<?php
declare(strict_types=1);

/**
 * Definicion declarativa de todo el contenido editable del sitio.
 * Cada entrada genera automaticamente su listado y su formulario en el panel.
 */
function admin_schema(): array
{
    $iconField = ['label' => 'Icono', 'type' => 'icon', 'hint' => 'Elija el icono que acompaña al elemento.'];
    $statusField = ['label' => 'Visible en el sitio', 'type' => 'checkbox', 'default' => 1];
    $sortField = ['label' => 'Orden', 'type' => 'number', 'hint' => 'Número menor aparece primero.'];

    return [

    // ------------------------------------------------------------- Slider --
    'slider' => [
        'table' => 'slides', 'title' => 'Slider principal', 'singular' => 'diapositiva',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'imagen',
        'hint'  => 'Cada diapositiva del carrusel de la portada. Puede cambiar imágenes, textos, botones y el orden.',
        'list'  => ['image' => 'Imagen', 'title' => 'Título', 'status' => 'Visible'],
        'fields' => [
            'eyebrow'   => ['label' => 'Texto pequeño superior', 'type' => 'text', 'max' => 160, 'hint' => 'Ej. «Más de 16 años diseñando en Guatemala».'],
            'title'     => ['label' => 'Título principal', 'type' => 'text', 'required' => true, 'max' => 200, 'full' => true],
            'highlight' => ['label' => 'Palabras destacadas', 'type' => 'text', 'max' => 160, 'hint' => 'Se muestran con el color degradado, al final del título.'],
            'subtitle'  => ['label' => 'Descripción', 'type' => 'textarea', 'max' => 500, 'full' => true],
            'image'     => ['label' => 'Imagen de fondo', 'type' => 'media', 'full' => true, 'hint' => 'Recomendado 1600×940 px o mayor.'],
            'image_alt' => ['label' => 'Texto alternativo de la imagen (SEO)', 'type' => 'text', 'max' => 200, 'full' => true],
            'align'     => ['label' => 'Alineación del texto', 'type' => 'select', 'options' => ['left' => 'Izquierda', 'center' => 'Centrada', 'right' => 'Derecha']],
            'btn1_text' => ['label' => 'Botón 1 · texto', 'type' => 'text', 'max' => 80],
            'btn1_url'  => ['label' => 'Botón 1 · enlace', 'type' => 'text', 'max' => 255, 'hint' => 'Ruta interna (/contacto/), enlace completo, «whatsapp» o «tel».'],
            'btn1_icon' => $iconField + ['label' => 'Botón 1 · icono'],
            'btn2_text' => ['label' => 'Botón 2 · texto', 'type' => 'text', 'max' => 80],
            'btn2_url'  => ['label' => 'Botón 2 · enlace', 'type' => 'text', 'max' => 255],
            'btn2_icon' => $iconField + ['label' => 'Botón 2 · icono'],
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // ---------------------------------------------------------- Servicios --
    'servicios' => [
        'table' => 'services', 'title' => 'Servicios', 'singular' => 'servicio',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'servicios',
        'hint'  => 'Los servicios se muestran en la portada, en la página de servicios y cada uno tiene su propia página con SEO.',
        'list'  => ['icon' => 'Icono', 'title' => 'Servicio', 'slug' => 'URL', 'status' => 'Visible'],
        'fields' => [
            'title'       => ['label' => 'Nombre del servicio', 'type' => 'text', 'required' => true, 'max' => 200],
            'short_title' => ['label' => 'Nombre corto (menús)', 'type' => 'text', 'max' => 80],
            'slug'        => ['label' => 'URL amigable', 'type' => 'slug', 'from' => 'title', 'hint' => 'Se usa en /servicios/URL/. Déjelo vacío para generarlo del nombre.'],
            'icon'        => $iconField,
            'excerpt'     => ['label' => 'Descripción corta', 'type' => 'textarea', 'max' => 500, 'full' => true, 'hint' => 'Aparece en las tarjetas de la portada.'],
            'body'        => ['label' => 'Descripción completa', 'type' => 'textarea', 'tall' => true, 'full' => true, 'hint' => 'Separe los párrafos con una línea en blanco.'],
            'features'    => ['label' => 'Qué incluye', 'type' => 'lines', 'full' => true, 'hint' => 'Una característica por línea.'],
            'image'       => ['label' => 'Imagen', 'type' => 'media', 'full' => true],
            'image_alt'   => ['label' => 'Texto alternativo de la imagen (SEO)', 'type' => 'text', 'max' => 200, 'full' => true],
            'price_text'  => ['label' => 'Texto de precio', 'type' => 'text', 'max' => 120, 'hint' => 'Ej. «Planes accesibles según su negocio».'],
            'btn_text'    => ['label' => 'Texto del botón', 'type' => 'text', 'max' => 80],
            'meta_title'  => ['label' => 'SEO · Título de la página', 'type' => 'text', 'max' => 200, 'full' => true, 'hint' => 'Ideal entre 50 y 60 caracteres.'],
            'meta_description' => ['label' => 'SEO · Meta descripción', 'type' => 'textarea', 'max' => 320, 'full' => true, 'hint' => 'Ideal entre 140 y 160 caracteres.'],
            'meta_keywords'    => ['label' => 'SEO · Palabras clave', 'type' => 'text', 'max' => 320, 'full' => true],
            'featured'   => ['label' => 'Destacar en la portada', 'type' => 'checkbox', 'default' => 1],
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // -------------------------------------------------------------- Planes --
    'planes' => [
        'table' => 'plans', 'title' => 'Planes', 'singular' => 'plan',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'planes',
        'hint'  => 'Bloques de planes que se muestran en la portada. Puede desactivar la sección completa desde «Secciones y textos».',
        'list'  => ['icon' => 'Icono', 'name' => 'Plan', 'price_text' => 'Precio', 'status' => 'Visible'],
        'fields' => [
            'name'       => ['label' => 'Nombre del plan', 'type' => 'text', 'required' => true, 'max' => 120],
            'tagline'    => ['label' => 'Frase descriptiva', 'type' => 'text', 'max' => 200, 'full' => true],
            'price_text' => ['label' => 'Texto de precio', 'type' => 'text', 'max' => 120],
            'icon'       => $iconField,
            'features'   => ['label' => 'Qué incluye', 'type' => 'lines', 'full' => true, 'hint' => 'Una línea por característica.'],
            'btn_text'   => ['label' => 'Texto del botón', 'type' => 'text', 'max' => 80],
            'btn_url'    => ['label' => 'Enlace del botón', 'type' => 'text', 'max' => 255],
            'featured'   => ['label' => 'Marcar como «Más solicitado»', 'type' => 'checkbox'],
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // ---------------------------------------------------------- Portafolio --
    'proyectos' => [
        'table' => 'projects', 'title' => 'Portafolio', 'singular' => 'proyecto',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'portafolio',
        'hint'  => 'Sustituya estos ejemplos por sus proyectos reales: imagen, nombre y enlace al sitio del cliente.',
        'list'  => ['image' => 'Imagen', 'title' => 'Proyecto', 'category' => 'Categoría', 'status' => 'Visible'],
        'fields' => [
            'title'       => ['label' => 'Nombre del proyecto', 'type' => 'text', 'required' => true, 'max' => 200],
            'category'    => ['label' => 'Categoría o sector', 'type' => 'text', 'max' => 80],
            'description' => ['label' => 'Descripción', 'type' => 'textarea', 'max' => 500, 'full' => true],
            'image'       => ['label' => 'Imagen', 'type' => 'media', 'full' => true],
            'image_alt'   => ['label' => 'Texto alternativo (SEO)', 'type' => 'text', 'max' => 200, 'full' => true],
            'url'         => ['label' => 'Enlace al sitio', 'type' => 'text', 'max' => 255, 'full' => true, 'hint' => 'Opcional. Si lo deja vacío la tarjeta no será un enlace.'],
            'sort_order'  => $sortField,
            'status'      => $statusField,
        ],
    ],

    // ------------------------------------------------------------- Proceso --
    'proceso' => [
        'table' => 'process_steps', 'title' => 'Proceso de trabajo', 'singular' => 'paso',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'engranaje',
        'hint'  => 'Los pasos que sigue con cada cliente, desde la cotización hasta la entrega.',
        'list'  => ['icon' => 'Icono', 'title' => 'Paso', 'status' => 'Visible'],
        'fields' => [
            'title'      => ['label' => 'Título del paso', 'type' => 'text', 'required' => true, 'max' => 160],
            'body'       => ['label' => 'Descripción', 'type' => 'textarea', 'full' => true],
            'icon'       => $iconField,
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // --------------------------------------------------------- Indicadores --
    'indicadores' => [
        'table' => 'stats', 'title' => 'Indicadores', 'singular' => 'indicador',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'grafica',
        'hint'  => 'Cifras animadas de la portada. Use solo datos reales de su empresa.',
        'list'  => ['value' => 'Valor', 'label' => 'Descripción', 'status' => 'Visible'],
        'fields' => [
            'value'      => ['label' => 'Valor', 'type' => 'text', 'required' => true, 'max' => 20, 'hint' => 'Si es un número se anima al hacer scroll (ej. 16).'],
            'prefix'     => ['label' => 'Prefijo', 'type' => 'text', 'max' => 10, 'hint' => 'Ej. «+» o «Q».'],
            'suffix'     => ['label' => 'Sufijo', 'type' => 'text', 'max' => 10, 'hint' => 'Ej. «+», «%».'],
            'label'      => ['label' => 'Descripción', 'type' => 'text', 'required' => true, 'max' => 120, 'full' => true],
            'icon'       => $iconField,
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // --------------------------------------------------------- Testimonios --
    'testimonios' => [
        'table' => 'testimonials', 'title' => 'Testimonios', 'singular' => 'testimonio',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'comilla',
        'hint'  => 'Publique únicamente comentarios reales de sus clientes. Los ejemplos vienen desactivados.',
        'list'  => ['name' => 'Cliente', 'role' => 'Cargo', 'rating' => 'Estrellas', 'status' => 'Visible'],
        'fields' => [
            'name'       => ['label' => 'Nombre del cliente', 'type' => 'text', 'required' => true, 'max' => 120],
            'role'       => ['label' => 'Cargo y empresa', 'type' => 'text', 'max' => 160],
            'body'       => ['label' => 'Comentario', 'type' => 'textarea', 'required' => true, 'full' => true],
            'rating'     => ['label' => 'Estrellas (1 a 5)', 'type' => 'number'],
            'avatar'     => ['label' => 'Foto', 'type' => 'media', 'full' => true],
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // ---------------------------------------------------------------- FAQ --
    'faqs' => [
        'table' => 'faqs', 'title' => 'Preguntas frecuentes', 'singular' => 'pregunta',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'documento',
        'hint'  => 'Estas preguntas generan datos estructurados FAQ para Google, que pueden mostrarse en los resultados de búsqueda.',
        'list'  => ['question' => 'Pregunta', 'status' => 'Visible'],
        'fields' => [
            'question'   => ['label' => 'Pregunta', 'type' => 'text', 'required' => true, 'max' => 255, 'full' => true],
            'answer'     => ['label' => 'Respuesta', 'type' => 'textarea', 'required' => true, 'full' => true],
            'sort_order' => $sortField,
            'status'     => $statusField,
        ],
    ],

    // ---------------------------------------------------------------- Blog --
    'blog' => [
        'table' => 'posts', 'title' => 'Actualidad Web', 'singular' => 'publicación',
        'order' => 'published_at DESC, id DESC', 'icon' => 'blog', 'timestamps' => false,
        'hint'  => 'Publicaciones del blog. Cada una tiene su propia URL y sus etiquetas SEO.',
        'list'  => ['image' => 'Imagen', 'title' => 'Título', 'published_at' => 'Publicado', 'status' => 'Visible'],
        'fields' => [
            'title'       => ['label' => 'Título', 'type' => 'text', 'required' => true, 'max' => 220, 'full' => true],
            'slug'        => ['label' => 'URL amigable', 'type' => 'slug', 'from' => 'title'],
            'excerpt'     => ['label' => 'Resumen', 'type' => 'textarea', 'max' => 500, 'full' => true],
            'body'        => ['label' => 'Contenido', 'type' => 'textarea', 'tall' => true, 'full' => true, 'hint' => 'Separe los párrafos con una línea en blanco.'],
            'image'       => ['label' => 'Imagen destacada', 'type' => 'media', 'full' => true],
            'image_alt'   => ['label' => 'Texto alternativo (SEO)', 'type' => 'text', 'max' => 200, 'full' => true],
            'author'      => ['label' => 'Autor', 'type' => 'text', 'max' => 120],
            'published_at' => ['label' => 'Fecha de publicación', 'type' => 'datetime'],
            'meta_title'  => ['label' => 'SEO · Título', 'type' => 'text', 'max' => 200, 'full' => true],
            'meta_description' => ['label' => 'SEO · Meta descripción', 'type' => 'textarea', 'max' => 320, 'full' => true],
            'meta_keywords'    => ['label' => 'SEO · Palabras clave', 'type' => 'text', 'max' => 320, 'full' => true],
            'status'      => $statusField,
        ],
    ],

    // ------------------------------------------------------------ Secciones --
    'secciones' => [
        'table' => 'blocks', 'title' => 'Secciones y textos', 'singular' => 'sección',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'documento', 'create' => false, 'delete' => false,
        'hint'  => 'Encabezados, textos y botones de cada sección del sitio. Desactive una sección para ocultarla completa.',
        'list'  => ['label' => 'Sección', 'title' => 'Título actual', 'status' => 'Visible'],
        'fields' => [
            'label'     => ['label' => 'Nombre de la sección', 'type' => 'text', 'readonly' => true],
            'eyebrow'   => ['label' => 'Texto pequeño superior', 'type' => 'text', 'max' => 160],
            'title'     => ['label' => 'Título', 'type' => 'text', 'max' => 255, 'full' => true],
            'subtitle'  => ['label' => 'Subtítulo', 'type' => 'textarea', 'max' => 500, 'full' => true],
            'body'      => ['label' => 'Texto largo', 'type' => 'textarea', 'tall' => true, 'full' => true],
            'image'     => ['label' => 'Imagen', 'type' => 'media', 'full' => true],
            'icon'      => $iconField,
            'btn_text'  => ['label' => 'Botón 1 · texto', 'type' => 'text', 'max' => 80],
            'btn_url'   => ['label' => 'Botón 1 · enlace', 'type' => 'text', 'max' => 255],
            'btn2_text' => ['label' => 'Botón 2 · texto', 'type' => 'text', 'max' => 80],
            'btn2_url'  => ['label' => 'Botón 2 · enlace', 'type' => 'text', 'max' => 255, 'hint' => 'Puede escribir «whatsapp» para enlazar al WhatsApp configurado.'],
            'extra'     => ['label' => 'Lista de puntos', 'type' => 'lines', 'full' => true, 'hint' => 'Solo se usa en la sección «Nosotros». Una línea por punto.'],
            'sort_order' => $sortField,
            'status'    => $statusField,
        ],
    ],

    // -------------------------------------------------------------- Paginas --
    'paginas' => [
        'table' => 'pages', 'title' => 'Páginas', 'singular' => 'página',
        'order' => 'sort_order ASC, id ASC', 'icon' => 'web', 'timestamps' => true,
        'hint'  => 'Páginas del sitio y su SEO. Las páginas del sistema no se pueden eliminar porque tienen un diseño propio.',
        'list'  => ['title' => 'Página', 'slug' => 'URL', 'template' => 'Diseño', 'status' => 'Visible'],
        'fields' => [
            'title'    => ['label' => 'Título', 'type' => 'text', 'required' => true, 'max' => 200],
            'slug'     => ['label' => 'URL amigable', 'type' => 'slug', 'from' => 'title'],
            'subtitle' => ['label' => 'Subtítulo', 'type' => 'text', 'max' => 255, 'full' => true],
            'template' => ['label' => 'Diseño de la página', 'type' => 'select', 'options' => [
                'page' => 'Página de texto', 'home' => 'Portada', 'services' => 'Servicios',
                'about' => 'Nosotros', 'portfolio' => 'Portafolio', 'blog' => 'Blog', 'contact' => 'Contacto',
            ]],
            'body'     => ['label' => 'Contenido', 'type' => 'textarea', 'tall' => true, 'full' => true],
            'image'    => ['label' => 'Imagen', 'type' => 'media', 'full' => true],
            'meta_title'       => ['label' => 'SEO · Título', 'type' => 'text', 'max' => 200, 'full' => true, 'hint' => 'Entre 50 y 60 caracteres es lo ideal para Google.'],
            'meta_description' => ['label' => 'SEO · Meta descripción', 'type' => 'textarea', 'max' => 320, 'full' => true, 'hint' => 'Entre 140 y 160 caracteres.'],
            'meta_keywords'    => ['label' => 'SEO · Palabras clave', 'type' => 'text', 'max' => 320, 'full' => true],
            'og_image' => ['label' => 'Imagen para redes sociales', 'type' => 'media', 'full' => true, 'hint' => 'Recomendado 1200×630 px.'],
            'robots'   => ['label' => 'Indexación', 'type' => 'select', 'options' => [
                '' => 'Usar la configuración general', 'index, follow' => 'Indexar y seguir enlaces',
                'noindex, follow' => 'No indexar, seguir enlaces', 'noindex, nofollow' => 'No indexar ni seguir',
            ]],
            'show_in_sitemap' => ['label' => 'Incluir en el sitemap.xml', 'type' => 'checkbox', 'default' => 1],
            'priority' => ['label' => 'Prioridad en el sitemap', 'type' => 'select', 'options' => [
                '1.0' => '1.0 — máxima', '0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.6' => '0.6', '0.5' => '0.5', '0.3' => '0.3 — baja',
            ]],
            'sort_order' => $sortField,
            'status'   => $statusField,
        ],
    ],

    // ----------------------------------------------------------------- Menú --
    'menu' => [
        'table' => 'menu_items', 'title' => 'Menú de navegación', 'singular' => 'enlace',
        'order' => 'location ASC, sort_order ASC, id ASC', 'icon' => 'servicios',
        'hint'  => 'Cada botón del menú tiene su propio icono. Puede crear enlaces para la cabecera o para el pie de página.',
        'list'  => ['icon' => 'Icono', 'label' => 'Texto', 'url' => 'Enlace', 'location' => 'Ubicación', 'status' => 'Visible'],
        'fields' => [
            'label'    => ['label' => 'Texto del botón', 'type' => 'text', 'required' => true, 'max' => 80],
            'url'      => ['label' => 'Enlace', 'type' => 'text', 'required' => true, 'max' => 255, 'hint' => 'Ruta interna como /servicios/ o enlace completo https://…'],
            'icon'     => $iconField,
            'location' => ['label' => 'Ubicación', 'type' => 'select', 'options' => ['header' => 'Cabecera (menú principal)', 'footer' => 'Pie de página']],
            'target'   => ['label' => 'Abrir en', 'type' => 'select', 'options' => ['_self' => 'La misma pestaña', '_blank' => 'Una pestaña nueva']],
            'is_button' => ['label' => 'Mostrar como botón destacado', 'type' => 'checkbox'],
            'sort_order' => $sortField,
            'status'   => $statusField,
        ],
    ],

    // ------------------------------------------------------------- Usuarios --
    'usuarios' => [
        'table' => 'users', 'title' => 'Usuarios', 'singular' => 'usuario',
        'order' => 'id ASC', 'icon' => 'usuarios',
        'hint'  => 'Personas con acceso al panel. Use contraseñas largas y no las comparta por correo.',
        'list'  => ['name' => 'Nombre', 'username' => 'Usuario', 'email' => 'Correo', 'last_login' => 'Último acceso', 'status' => 'Activo'],
        'fields' => [
            'name'     => ['label' => 'Nombre completo', 'type' => 'text', 'required' => true, 'max' => 120],
            'username' => ['label' => 'Usuario', 'type' => 'text', 'required' => true, 'max' => 60],
            'email'    => ['label' => 'Correo electrónico', 'type' => 'email', 'required' => true, 'max' => 160],
            'password' => ['label' => 'Contraseña', 'type' => 'password', 'full' => true, 'hint' => 'Déjelo vacío para conservar la contraseña actual. Mínimo 8 caracteres.'],
            'role'     => ['label' => 'Rol', 'type' => 'select', 'options' => ['admin' => 'Administrador', 'editor' => 'Editor']],
            'status'   => ['label' => 'Puede iniciar sesión', 'type' => 'checkbox', 'default' => 1],
        ],
    ],

    ];
}
