# Proyecto de cambios para UNA web de cliente (creada por Servicom, «Tu web en 5 minutos»)

Eres Claude Code. Este proyecto contiene **una web de WordPress ya publicada** y tu trabajo es hacerle los cambios que pida el dueño de Servicom,
entregando **un ZIP con solo lo que cambió**, para que él lo suba a la raíz del sitio y lo descomprima. No hay acceso al servidor: todo se entrega por ZIP.

## Qué hay en esta carpeta
- `wp-content/` — copia de la web: tema `themes/servicom`, mu-plugin `mu-plugins/servicom-core` (el motor LUXE y el editor), `uploads/` (imágenes).
- Un volcado `*.sql` de la base de datos — **solo para LEER** el contenido actual. Jamás se devuelve ni se reimporta.
- La **URL** de la web publicada (te la dará el dueño). Si no puedes abrirla, pídele capturas de pantalla.
- `plantillas/`, `herramientas/`, `docs/` (este kit). `salida/` y `entregas/` las creas tú.

## Reglas de oro (no negociables)
1. **Nunca** devuelvas la base de datos, `wp-config.php`, `.htaccess`, claves, ni archivos de log. El empaquetador los rechaza.
2. **No inventes datos del negocio** (teléfonos, direcciones, cifras, premios, testimonios). Si falta algo, dilo y pídelo; el resto sí puedes redactarlo.
3. Textos en español, trato de **«usted»**, tono profesional y cálido. Sin faltas de ortografía.
4. Diseño: lujo, compacto (sin espacios vacíos), colores **siempre derivados del logo**, iconos premium, botones flotantes de llamada/WhatsApp/correo y formulario de contacto. No rompas nada de esto.
5. Cambios **mínimos y reversibles**: toca solo lo pedido. No actualices WordPress ni plugins. No toques WooCommerce salvo que lo pidan.
6. El PHP debe funcionar en **PHP 8.0 a 8.4**. Comprueba cada archivo con `php -l`.
7. Todo lo que entregues se prueba antes: relee tu diff y, si puedes, valida con la URL o con capturas. Di con honestidad qué probaste y qué no.

## Dos tipos de cambio
### A) Cambios de contenido (lo más común): textos, teléfonos, servicios, secciones, colores, redes, horario, logo
El contenido vive en la base de datos (opción `sc_site` y datos del negocio `sc_*` del tema). **No lo cambies editando SQL.** Se hace con un archivo que se ejecuta
**una sola vez** al abrir la web y se borra solo, usando las **mismas operaciones del editor visual** (mismas validaciones y con «Deshacer» disponible):

1. Lee el contenido actual: `php herramientas/leer-sc-site.php volcado.sql --theme-mods` (imprime JSON; muestra rutas como `pages.home.sections.0.data.title`).
   Verifica además en `wp-content/mu-plugins/servicom-core/includes/editor.php` los campos exactos de cada operación (funciones `sc_ed_op_*`). **Cada ruta debe existir en el contenido actual**; si no existe la operación falla con un aviso claro.
2. Copia `plantillas/aplicar-cambios.php` a `salida/wp-content/mu-plugins/sc-cambios-AAAAMMDD_HHMM.php` (ID = fecha y hora actuales) y:
   - cambia la línea `$id = 'sc_cambio_AAAAMMDD_HHMM'` por el mismo ID y la descripción de la cabecera;
   - llena `$lotes` (máx. 40 operaciones por lote). Si algo falla no se aplica y el motivo queda en la opción `sc_cambio_<ID>_error`.
3. Operaciones disponibles (campo `op`):
   | op | campos | para qué |
   |---|---|---|
   | `text` | `path`, `value` | cambiar un texto (`pages.home.sections.N.data.title`, `services.N.nombre`, `footer.texto`…) |
   | `link` | `path`, `text`, `url` | un botón/enlace (`url` puede ser `wa`, `tel`, `mail`, `page:servicios`, `#ancla` o `https://…`) |
   | `icon` | `path`, `key` | cambiar un icono (claves en `theme/servicom/inc/luxe-icons.php`) |
   | `img` | `path`, `id` | cambiar una imagen por un adjunto de la mediateca (`id`) |
   | `sec_on` | `path` (`pages.home.sections.N`), `sid` (opcional: el `id` de la sección, para verificar), `on` (true/false) | mostrar u ocultar una sección |
   | `sec_move` | `path` (`pages.home.sections.N`), `sid` (opcional), `dir` (1 / -1) o `to` (posición) | mover una sección |
   | `list_add` / `list_del` / `list_move` | `path`, `at`/`index`, `dir` | listas (preguntas frecuentes, valores, pasos, galería) |
   | `svc_add` / `svc_dup` / `svc_on` / `svc_del` | `nombre`, `n`, `on` | servicios (crea/borra también su página) |
   | `biz` | `values` (`nombre, telefono, whatsapp, whatsapp_msg, correo, direccion, mapa_url, horario, facebook, instagram, tiktok, youtube, x, linkedin`) | datos del negocio |
   | `logo` | `id` | logo |
   | `design` | `primary`, `accent`, `mood` | colores y ambiente (**el color principal debe seguir saliendo del logo**) |
4. Para algo que las operaciones no cubren (p. ej. subir una imagen nueva), usa funciones normales de WordPress en el bloque «Otras acciones» de la plantilla y los archivos nuevos dentro de `salida/wp-content/uploads/...`.

### B) Cambios de código o diseño (tema/mu-plugin)
Edita el archivo bajo `wp-content/themes/servicom/` o `wp-content/mu-plugins/servicom-core/`, **cópialo con su misma ruta a `salida/`** y entrégalo en el ZIP. Lee antes
`docs/CONTRATO-LUXE.md` (modelo de contenido, secciones, iconos, ownership). No cambies nombres de funciones públicas ni atributos `data-sc` (los usa el editor).
Si tocas CSS/JS, súbele la versión o cambia el nombre para vencer la caché del navegador.

## Entrega (siempre así)
1. Deja en `salida/` **solo** lo que cambiaste, con su ruta desde la raíz del sitio (`salida/wp-content/...`).
2. `bash herramientas/empaquetar.sh "descripción corta"` → crea `entregas/cambios-AAAAMMDD-HHMM.zip` (revisa sintaxis PHP, rechaza archivos prohibidos).
3. Dile al dueño, **un paso a la vez y con palabras sencillas**:
   1. Subir el ZIP al administrador de archivos de cPanel, a la carpeta raíz del sitio (la que contiene `wp-config.php`).
   2. Clic derecho → **Extract**, aceptando reemplazar archivos.
   3. Abrir la web **una vez** (los cambios de contenido se aplican en ese momento y el archivo se borra solo).
   4. Si algo se ve mal: en el sitio, botón **Deshacer** del editor, o devolver el ZIP anterior.
4. Termina con un resumen: qué cambió, qué NO pudiste probar y cualquier dato que falte y no inventaste.

## Cuando el dueño ya hizo cambios por su cuenta
Antes de cada tarea pídele un volcado **nuevo** (la web pudo cambiar desde el editor). Nunca des por bueno un volcado viejo.
