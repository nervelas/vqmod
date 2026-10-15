# REPORTE DE VERIFICACIÓN (sección 7) — Grúas Express Towing

Fecha: 15-oct-2026 · Paquete: `gruas-express-towing.zip` · Resultado global: **TODO PASA**.

## Resumen por punto

| # | Verificación | Resultado |
|---|---|---|
| 1 | `php -l` en todos los PHP; prueba en PHP 8.0 | **21/21 archivos sin errores en PHP 8.3.6 y en PHP 8.0.30** |
| 2 | Carpeta limpia (simula la raíz) + `php -S`: auto-instalación y generación sin errores ni *warnings* | **OK** en PHP 8.0.30 (`php -S`) y en Apache + `mod_php` 8.3. 0 líneas de aviso en `php-error.log` |
| 3 | Recorrido de todas las páginas: sin 404/500, enlaces, imágenes, botones | **OK**: 4 páginas + 404/403/500, sitemap, robots, favicons y **69 recursos** (imágenes, CSS, JS, fuentes, videos) responden 200; 0 recursos de terceros |
| 4 | Comparación contra `INVENTARIO.md` punto por punto | **OK**: textos exactos de las 4 páginas, 4 enlaces de menú, `tel:35567904`, WhatsApp `50235567904`, `mailto:info@agroinco.com`, logo y logo móvil, barra superior, pie, galerías (22 y 16 imágenes, orden original), 2 videos en orden, orden de secciones, title de inicio idéntico |
| 5 | Formulario, login, 2FA, edición de cada tipo de contenido, Publicar | **OK**: ver §3 |
| 6 | Capturas 375 / 768 / 1440 px (y 320 px) | **OK**: 12 páginas×tamaño sin desborde horizontal, sin textos cortados ni elementos montados; revisión visual con correcciones (ver §4). Capturas en `tests/screenshots/` |
| 7 | Consola del navegador | **0 errores de JavaScript y 0 recursos fallidos** en las 12 combinaciones, panel incluido |
| 8 | Paridad con Xara | **OK**: el ZIP contiene solo 2 `.php` públicos (`admin/index.php`, `enviar.php`); 70 scripts de prueba (`.php`, `.phtml`, `.phar`, `.php5`, `.PHP`, `.php.jpg`, `.php.webp`) colocados en 10 carpetas públicas no se ejecutan ni se sirven (403); modo bloqueo se activa al modificar un PHP, al aparecer un `.php` en `/uploads` o en una carpeta pública |
| 9 | Auditoría de seguridad | **OK**: 0 coincidencias de funciones prohibidas; subidas maliciosas rechazadas o neutralizadas; SQLi, XSS y *path traversal* fallan; BD/logs/config/respaldos dan 403; cabeceras verificadas |

## 1. Entornos de prueba

* **Apache 2.4.58 + `mod_php` 8.3** con `AllowOverride All`, ejecutando el `.htaccess` real (simula un hosting compartido; el ZIP descomprimido en la raíz): **265/265 pruebas OK**.
* **PHP 8.0.30 (`php -S`)** — no procesa `.htaccess`, por lo que se ejecutan solo las pruebas que dependen de PHP (formulario, panel, 2FA, edición, publicación, subidas, bloqueo): **146/146 OK**, con GD+WebP y Argon2id.
* **Chromium (Playwright)**: capturas, consola, desbordes y flujo real del formulario.
* PHP 8.1/8.2 no se instalaron; el código evita sintaxis posterior a 8.0 y se validó con 8.0.30 y 8.3.6.

## 2. Hallazgos durante la verificación (todos corregidos)

| Hallazgo | Corrección |
|---|---|
| Con `php_flag engine off` en la raíz, `admin/index.php` se mostraba como **texto** (fuga de código fuente) | Se reactiva el motor solo para `admin/index.php` y `enviar.php`; verificado |
| El monitor marcó como PHP bytes `<?` al azar dentro de una imagen WEBP (falso positivo → bloqueo en la primera visita) | Ahora solo detecta `<?php` / `<?=` reales, en cabecera y cola del archivo |
| El saneador de HTML eliminaba enlaces en el 2.º paso (guardar → publicar) | Hecho idempotente; además elimina `<script>/<style>/<iframe>/<svg>` con su contenido |
| El escáner marcaba `$pdo->exec()` y mis propias reglas del `.htaccess` | Reglas afinadas (distinguen método vs función y directivas reales) y probadas con 8 muestras de malware |
| Mi regla de User-Agent bloqueaba herramientas legítimas y atrapaba la página 403 en un bucle | Lista reducida a escáneres; páginas de error exentas del filtro |
| La regla «solo tipos permitidos» también bloqueaba la carpeta `/sobre-nosotros/` | Limitada a nombres con extensión |
| Tarjetas de contacto partían el teléfono/correo a mitad de palabra (móvil y tablet) | Maquetación nueva; verificado en 320/375/768/1440 |
| Hash inicial Argon2id podía no verificarse en hostings sin Argon2 | El hash inicial es bcrypt (universal); luego usa Argon2id si existe |
| Si GD no tuviera WebP, la subida fallaba sin explicación | Mensaje claro + indicador en Seguridad |

## 3. Pruebas funcionales y de seguridad (resumen)

* **Formulario:** token HMAC (anti-replay, edad mínima 2 s), honeypot, validación servidor/cliente, límite por IP (429), origen externo y tipos de contenido rechazados, inyección de cabeceras (`\r\nBcc:`) rechazada, XSS guardado se muestra escapado en el panel, copia en BD. **Flujo real en Chromium:** al pulsar «Enviar mensaje» se abre `api.whatsapp.com/send?phone=50235567904&text=…` con nombre, teléfono, tipo de servicio, ubicación, correo y mensaje; la copia queda en Mensajes.
* **Panel:** contraseña HTTP (401 + `WWW-Authenticate`), login con error genérico, CSRF falso/ausente rechazado, SQLi en login, cambio obligatorio de la clave inicial y política de contraseñas, **2FA TOTP** (vector RFC 6238 verificado, código erróneo rechazado, anti-reutilización), bloqueo tras 8 intentos fallidos, restricción por IP, cierre de sesión, cookie `HttpOnly`+`SameSite=Strict`, `noindex`, `no-store`, CSP sin inline.
* **Edición + Publicar:** SEO por página, portada, textos, bloques (eliminar/agregar), galería, formulario y servicios, teléfono/WhatsApp/correo/dirección/horario/red social/menú/colores/pie, imágenes (subir, reemplazar, alt, eliminar con protección si están en uso), favicon. Lo guardado **no** cambia el sitio hasta pulsar Publicar; después se reflejan páginas, JSON-LD, `sitemap.xml` y `theme.css`.
* **Subidas:** rechazadas `.php`, `.php.jpg`, `.phtml`, PHP renombrado a `.jpg`, GIF disfrazado, SVG con script, `.htaccess`, firma/extensión que no coinciden, `../` en el nombre, archivos diminutos y >8 MB. **Neutralizadas:** JPEG con PHP añadido, JPEG con PHP en un comentario y PNG con PHP en un chunk `tEXt` se aceptan como imagen pero se recodifican a WEBP **sin** el código incrustado.
* **Ataques al sitio público:** 10 patrones de SQLi/XSS/traversal/inclusión y 3 User-Agents de escáneres → 403; 6 variantes de *traversal* a `_core/data/config.json` bloqueadas; BD, logs, `.htpasswd`, semilla, `README.txt`, `.htaccess` → 403.
* **Modo bloqueo:** al modificar un PHP, aparecer un `.php` en `/uploads` o en `assets/js`, el panel responde 503, el formulario responde 503 (JSON) y el sitio público sigue en 200; la alerta por correo se intenta (en el entorno de prueba `mail()` no tiene sendmail y queda registrado). Una página pública alterada (iframe inyectado) genera alerta sin bloqueo y se restaura con Publicar. Recuperación documentada verificada.
* **Escáner:** detecta las 8 muestras (JS ofuscado, iframes ocultos, script externo, código dentro de `.webp`/`.mp4`, PHP con comandos/base64/gzinflate/`preg_replace /e`/include de `$_GET`) y queda limpio al retirarlas.
* **Ruta de admin personalizable:** renombrar `admin/` → `panel-x7/` antes de la primera visita: instala, la cookie y los formularios usan la nueva ruta, Basic Auth y monitor funcionan.

## 4. Revisión visual (capturas)

Revisadas a 375, 768 y 1440 px (más 320 px) en las 4 páginas, menú móvil abierto, lightbox, errores de validación del formulario y el panel en móvil/escritorio. Se corrigieron: partición de texto en las tarjetas de contacto, ícono de la galería, color del estado «Opcional» en el panel. Sin espacios vacíos ni bloques desbalanceados; la galería usa columnas equilibradas.

## 5. Limitaciones conocidas

* Las pruebas con `.htaccess` se hicieron en Apache + `mod_php`; **LiteSpeed no estaba disponible** (usa el mismo `.htaccess`; se espera el mismo resultado, pero conviene repetir las pruebas de §8 del plan en el hosting real).
* `TRACE` y la cabecera `Server:` son de configuración de servidor (ver `ANALISIS_SEGURIDAD.md §5`).
* El envío de correo (`mail()`/SMTP) no se pudo probar de extremo a extremo en el entorno de pruebas (no hay servidor de correo); el código SMTP no se probó contra un servidor real. El WhatsApp y la copia en el panel siempre funcionan.
* El 2FA se configura con la clave manual (sin código QR).

## 6. Revisión de diseño v2 (a pedido del cliente)

| Pedido | Resultado |
|---|---|
| Página **blanca** y con los **colores del logo** | Fondo blanco; azul `#11557B`, azul oscuro `#0A2A40` y rojo `#BF0000` del logo (rayas rojo/azul en cabecera, bandas y pie). Los colores son editables en Panel > Ajustes |
| Contenido a **ancho completo** | Bandas de sección a todo el ancho, contenedor sin límite de 1200 px (hasta 1920 px), galería de 2/3/4/5 columnas, portada a doble columna |
| Títulos **menos grandes** | Título principal 1.7–3.1 rem (antes hasta 5.6 rem); títulos de sección 1.2–1.8 rem |
| **Iconos premium en el menú** | Cada opción del menú (escritorio y panel móvil) lleva un ícono SVG propio dentro de un botón con degradado; el ícono es editable por opción en Panel > Ajustes > Menú |
| **El contenido debe ser igual** | Se eliminaron las mayúsculas forzadas por CSS (ahora cada texto se ve con las mayúsculas del original) y se restauró el orden del original en la portada (título, subtítulo, texto, botón). **Prueba automática: los 81 textos del contenido original (extraídos de la base de datos de WordPress) aparecen idénticos en el sitio nuevo.** Lo único añadido por pedido del cliente: formulario con tipo de servicio/ubicación que envía a WhatsApp, botones flotantes, y `alt`/meta descriptions para SEO |

## Apéndice: pruebas automatizadas (Apache + `mod_php`, 265)
- ✅ GET / -> 200
- ✅ GET /sobre-nosotros/ -> 200
- ✅ GET /servicio-de-gruas-24-7/ -> 200
- ✅ GET /contactenos/ -> 200
- ✅ 404 personalizada (sin rutas/versiones)
- ✅ GET /sitemap.xml -> 200
- ✅ GET /robots.txt -> 200
- ✅ GET /favicon.ico -> 200
- ✅ GET /favicon.png -> 200
- ✅ GET /apple-touch-icon.png -> 200
- ✅ GET /403.html -> 200
- ✅ GET /500.html -> 200
- ✅ sitemap.xml lista las 4 URLs originales
- ✅ robots.txt con Sitemap y sin revelar /admin
- ✅ Todos los enlaces/imágenes/CSS/JS/fuentes/videos responden 200 (69 recursos)
- ✅ Cero recursos de terceros (CSS/JS/fuentes/imágenes)
- ✅ Sin <script>/<style>/style="" inline (CSP estricta)
- ✅ CSS sin @import ni url() externos
- ✅ Textos exactos en / (22)
- ✅ Textos exactos en /sobre-nosotros/ (9)
- ✅ Textos exactos en /servicio-de-gruas-24-7/ (7)
- ✅ Textos exactos en /contactenos/ (8)
- ✅ /: botón llamar tel:35567904
- ✅ /: WhatsApp api.whatsapp.com/send?phone=50235567904
- ✅ /: mailto:info@agroinco.com (destino original)
- ✅ /: menú con las 4 URLs originales
- ✅ /: logo + logo móvil
- ✅ /sobre-nosotros/: botón llamar tel:35567904
- ✅ /sobre-nosotros/: WhatsApp api.whatsapp.com/send?phone=50235567904
- ✅ /sobre-nosotros/: mailto:info@agroinco.com (destino original)
- ✅ /sobre-nosotros/: menú con las 4 URLs originales
- ✅ /sobre-nosotros/: logo + logo móvil
- ✅ /servicio-de-gruas-24-7/: botón llamar tel:35567904
- ✅ /servicio-de-gruas-24-7/: WhatsApp api.whatsapp.com/send?phone=50235567904
- ✅ /servicio-de-gruas-24-7/: mailto:info@agroinco.com (destino original)
- ✅ /servicio-de-gruas-24-7/: menú con las 4 URLs originales
- ✅ /servicio-de-gruas-24-7/: logo + logo móvil
- ✅ /contactenos/: botón llamar tel:35567904
- ✅ /contactenos/: WhatsApp api.whatsapp.com/send?phone=50235567904
- ✅ /contactenos/: mailto:info@agroinco.com (destino original)
- ✅ /contactenos/: menú con las 4 URLs originales
- ✅ /contactenos/: logo + logo móvil
- ✅ /: galería con 22 imágenes
- ✅ /servicio-de-gruas-24-7/: galería con 16 imágenes
- ✅ /: 2 videos en el orden original
- ✅ /: orden de secciones = original (bienvenida, videos, precio, tarjetas, garantía, galería)
- ✅ Menú con íconos (escritorio y móvil): 8 íconos por página
- ✅ Sin mayúsculas forzadas por CSS (el contenido se ve con las mayúsculas del original)
- ✅ Fondo blanco (color de fondo #fff en el tema)
- ✅ Paridad de textos: los 81 textos originales de WordPress aparecen idénticos
- ✅ Todas las imágenes tienen alt
- ✅ /: title, description, canonical, Open Graph, JSON-LD LocalBusiness
- ✅ /sobre-nosotros/: title, description, canonical, Open Graph, JSON-LD LocalBusiness
- ✅ /servicio-de-gruas-24-7/: title, description, canonical, Open Graph, JSON-LD LocalBusiness
- ✅ /contactenos/: title, description, canonical, Open Graph, JSON-LD LocalBusiness
- ✅ Title de inicio idéntico al original
- ✅ JSON-LD: teléfono +50235567904 y correo
- ✅ Formulario: campos y destino WhatsApp 50235567904
- ✅ Content-Security-Policy estricta (sin unsafe-inline/unsafe-eval)
- ✅ Cabecera x-frame-options
- ✅ Cabecera x-content-type-options
- ✅ Cabecera referrer-policy
- ✅ Cabecera permissions-policy
- ✅ X-Frame-Options DENY / nosniff
- ✅ HSTS (sobre HTTPS)
- ✅ Directiva HSTS presente en .htaccess
- ✅ Método PUT bloqueado
- ✅ Método DELETE bloqueado
- ✅ Método PATCH bloqueado
- ✅ Método OPTIONS bloqueado
- ✅ Método HEAD permitido
- ✅ ZIP: .php públicos = exactamente admin/index.php y enviar.php
- ✅ ZIP: carpetas públicas sin PHP (uploads, media, assets, páginas)
- ✅ ZIP: sin node_modules, .git, tools ni código fuente de build
- ✅ 403/404 en /_core/lib/bootstrap.php
- ✅ 403/404 en /_core/lib/admin_app.php
- ✅ 403/404 en /_core/seed/content.json
- ✅ 403/404 en /_core/data/config.json
- ✅ 403/404 en /_core/data/baseline.json
- ✅ 403/404 en /_core/data/php-error.log
- ✅ 403/404 en /_core/data/.htpasswd
- ✅ 403/404 en /_core/.htaccess
- ✅ 403/404 en /.htaccess
- ✅ 403/404 en /README.txt
- ✅ 403/404 en /admin/.htaccess
- ✅ Ningún script colocado en carpetas públicas se ejecuta ni se sirve (70 pruebas)
- ✅ enviar.php responde (único PHP público) y no es texto
- ✅ admin/index.php exige contraseña HTTP (401)
- ✅ Archivos de prueba retirados
- ✅ Motor PHP desactivado en .htaccess raíz y carpetas públicas
- ✅ .user.ini con display_errors/expose_php/allow_url_*/disable_functions
- ✅ Filtro bloquea /?id=1' UNION SELECT 1,2,3--
- ✅ Filtro bloquea /?q=1%20union%20select%20password%20from%20users
- ✅ Filtro bloquea /?x=<script>alert(1)</script>
- ✅ Filtro bloquea /?x=%3Cscript%3Ealert(1)%3C/script%3E
- ✅ Filtro bloquea /?f=../../etc/passwd
- ✅ Filtro bloquea /?f=..%2f..%2fetc%2fpasswd
- ✅ Filtro bloquea /?a=php://filter/convert.base64-encode/resource=in
- ✅ Filtro bloquea /?a=1;DROP%20TABLE%20users
- ✅ Filtro bloquea /?s=sleep(5)
- ✅ Filtro bloquea /?x=javascript:alert(1)
- ✅ Path traversal bloqueado /uploads/../_core/data/config.json
- ✅ Path traversal bloqueado /%2e%2e/_core/data/config.json
- ✅ Path traversal bloqueado /uploads/%2e%2e/%2e%2e/_core/seed/install.json
- ✅ Path traversal bloqueado /assets/..%2f_core/data/config.json
- ✅ Path traversal bloqueado /uploads/..%252f_core/data/config.json
- ✅ Path traversal bloqueado /..%00/etc/passwd
- ✅ User-Agent malicioso bloqueado: sqlmap/1.5
- ✅ User-Agent malicioso bloqueado: Nikto/2.1.6
- ✅ User-Agent malicioso bloqueado: Mozilla/5.0 (compatible; Acunetix)
- ✅ /wp-login.php -> 410/403/404 (WordPress ya no existe)
- ✅ /wp-admin/ -> 410/404
- ✅ 301 /?page_id=14 -> /contactenos/
- ✅ 301 imagen antigua de WordPress -> /uploads/sg-1.webp
- ✅ /index.php (WordPress) -> 403: ningún PHP público fuera de los 2 puntos
- ✅ 301 /sobre-nosotros -> /sobre-nosotros/ (barra final)
- ✅ GET token JSON
- ✅ Token demasiado reciente rechazado
- ✅ Envío válido -> ok
- ✅ Copia guardada en la base de datos
- ✅ Token ya usado (anti-replay) rechazado
- ✅ Honeypot: respuesta "ok" pero no se guarda
- ✅ Validación servidor: nombre vacío -> 422
- ✅ Validación servidor: teléfono inválido -> 422
- ✅ Validación servidor: correo con inyección de cabeceras -> 422
- ✅ Validación servidor: sin servicio -> 422
- ✅ Validación servidor: mensaje corto -> 422
- ✅ Límite de envíos por IP (429)
- ✅ Content-Type no permitido rechazado
- ✅ Origen externo rechazado
- ✅ Mensaje con HTML/XSS aceptado como texto
- ✅ Panel sin contraseña HTTP -> 401 con WWW-Authenticate: Basic
- ✅ Contraseña HTTP incorrecta -> 401
- ✅ Con contraseña HTTP aparece el login
- ✅ Panel: noindex + no-store + CSP (PHP)
- ✅ Cookie de sesión HttpOnly + SameSite=Strict
- ✅ Login incorrecto: mensaje genérico
- ✅ Usuario inexistente: mismo mensaje genérico
- ✅ POST sin token CSRF rechazado
- ✅ POST con token CSRF falso rechazado
- ✅ SQLi en login no entra
- ✅ Login correcto exige cambio de contraseña inicial
- ✅ Contraseña débil rechazada
- ✅ Cambio de contraseña inicial OK
- ✅ Vista panel carga sin errores
- ✅ Vista paginas carga sin errores
- ✅ Vista pagina&s=inicio carga sin errores
- ✅ Vista pagina&s=sobre-nosotros carga sin errores
- ✅ Vista pagina&s=servicio-de-gruas-24-7 carga sin errores
- ✅ Vista pagina&s=contactenos carga sin errores
- ✅ Vista medios carga sin errores
- ✅ Vista ajustes carga sin errores
- ✅ Vista mensajes carga sin errores
- ✅ Vista seguridad carga sin errores
- ✅ Vista respaldos carga sin errores
- ✅ Vista registro carga sin errores
- ✅ Vista cuenta carga sin errores
- ✅ 2FA: se muestra la clave TOTP
- ✅ 2FA: código incorrecto rechazado
- ✅ 2FA: activación con código válido
- ✅ Login pide el segundo factor
- ✅ 2FA: código erróneo en login rechazado
- ✅ 2FA: login con código válido
- ✅ 2FA: un código ya usado no se puede reutilizar
- ✅ 2FA: con el contador restablecido el código vigente entra
- ✅ Editor de inicio: bloques cargados
- ✅ Guardar página de inicio
- ✅ Aviso de cambios sin publicar
- ✅ Antes de publicar el sitio público no cambia
- ✅ Publicar: mensaje de éxito
- ✅ Publicar regenera la página con título/SEO nuevos
- ✅ Héroe: subtítulo y botón con marcador {phone}
- ✅ XSS filtrado: sin <script>, onerror, onclick, iframe ni javascript:
- ✅ Enlace seguro conservado con rel=noopener
- ✅ Guardar ajustes
- ✅ Teléfono nuevo en todo el sitio (tarjeta, barra, héroe, botones)
- ✅ WhatsApp nuevo en enlaces y botón flotante
- ✅ Correo visible nuevo
- ✅ Dirección y horario aparecen en el pie
- ✅ Red social aparece con ícono y rel=noopener
- ✅ Menú editado
- ✅ Color editable (theme.css regenerado)
- ✅ JSON-LD refleja los datos nuevos
- ✅ Cambios en sitemap/robots (lastmod hoy)
- ✅ Opciones del formulario editables (escapadas)
- ✅ Servicio: 3 bloques
- ✅ Bloque eliminado y bloque nuevo agregado
- ✅ Rechazado: Archivo .php
- ✅ Rechazado: Archivo .php.jpg
- ✅ Rechazado: .phtml con tipo image/jpeg
- ✅ Rechazado: .jpg que en realidad es PHP
- ✅ Rechazado: .jpg que es texto con cabecera GIF
- ✅ Rechazado: GIF real renombrado a .jpg
- ✅ Rechazado: SVG con script
- ✅ Rechazado: .htaccess
- ✅ Rechazado: .jpg con firma PNG (extensión no coincide)
- ✅ Rechazado: Nombre con ../
- ✅ Rechazado: Archivo vacío/diminuto
- ✅ Rechazado: Mayor a 8 MB
- ✅ Ningún archivo malicioso llegó a /uploads
- ✅ /uploads solo contiene .webp (+ index.html/.htaccess)
- ✅ Aceptada y recodificada: JPEG con PHP añadido al final
- ✅   sin código incrustado y WEBP real: i859e69606e09f8531c2f.webp
- ✅   sin código incrustado y WEBP real: i859e69606e09f8531c2f-t.webp
- ✅ Aceptada y recodificada: JPEG con PHP en comentario
- ✅   sin código incrustado y WEBP real: ib5e13ac9b1808a2eb6ba.webp
- ✅   sin código incrustado y WEBP real: ib5e13ac9b1808a2eb6ba-t.webp
- ✅ Aceptada y recodificada: PNG con PHP en chunk tEXt
- ✅   sin código incrustado y WEBP real: iae60cd70c7965701d1a4.webp
- ✅   sin código incrustado y WEBP real: iae60cd70c7965701d1a4-t.webp
- ✅ Aceptada y recodificada: WEBP válido
- ✅   sin código incrustado y WEBP real: i5e42973d9bba2d8bbb24.webp
- ✅   sin código incrustado y WEBP real: i5e42973d9bba2d8bbb24-t.webp
- ✅ Aceptada y recodificada: JPG válido
- ✅   sin código incrustado y WEBP real: ie5e03a281f168be9d59c-t.webp
- ✅   sin código incrustado y WEBP real: ie5e03a281f168be9d59c.webp
- ✅ Biblioteca lista las imágenes subidas
- ✅ Eliminar imagen sin uso
- ✅ No permite eliminar una imagen en uso
- ✅ Reemplazar imagen
- ✅ Editar texto alternativo
- ✅ Mensajes del formulario listados
- ✅ XSS almacenado se muestra escapado en el panel
- ✅ Se encontró el mensaje con XSS
- ✅ Exportar CSV (con protección de fórmulas)
- ✅ Crear respaldo
- ✅ Descargar respaldo (SQLite válido)
- ✅ Descargar respaldo completo ZIP
- ✅ Monitor de integridad: todo en orden
- ✅ Escáner de malware: sin hallazgos en el sitio limpio
- ✅ Registro muestra accesos, fallos y cambios
- ✅ Escáner detecta la muestra assets/js/mal1.js
- ✅ Escáner detecta la muestra assets/js/mal2.js
- ✅ Escáner detecta la muestra sobre-nosotros/inj.html
- ✅ Escáner detecta la muestra contactenos/ext.html
- ✅ Escáner detecta la muestra uploads/zz-codigo.webp
- ✅ Escáner detecta la muestra media/zz-codigo.mp4
- ✅ Escáner detecta la muestra admin/assets/web.php
- ✅ Escáner detecta la muestra assets/css/o.php
- ✅ Escáner: limpio otra vez al retirar las muestras
- ✅ Cambiar contraseña HTTP (htpasswd con hash apr1; el servidor pide las nuevas credenciales)
- ✅ La contraseña HTTP anterior ya no funciona
- ✅ La nueva contraseña HTTP funciona
- ✅ Cerrar sesión: el panel pide login de nuevo
- ✅ Tras 8 fallos el login se bloquea temporalmente
- ✅ Aun con la contraseña correcta sigue bloqueado
- ✅ Restricción por IP: IP no autorizada -> 403
- ✅ Sin restricción vuelve a funcionar
- ✅ Estado normal antes de la prueba
- ✅ Formulario activo antes de la prueba
- ✅ PHP modificado -> panel desactivado (503, modo bloqueo)
- ✅ Archivo de bloqueo creado
- ✅ PHP modificado -> formulario desactivado (503 JSON)
- ✅ El sitio público estático sigue funcionando: /
- ✅ El sitio público estático sigue funcionando: /sobre-nosotros/
- ✅ El sitio público estático sigue funcionando: /servicio-de-gruas-24-7/
- ✅ El sitio público estático sigue funcionando: /contactenos/
- ✅ Auditoría registró el bloqueo
- ✅ Alerta por correo intentada (mail() no entrega en el sandbox, se registra el intento)
- ✅ Recuperación (restaurar + borrar lockdown.flag y baseline.json) reactiva el panel
- ✅ Aparece un .php en /uploads -> modo bloqueo
- ✅ Retirado el archivo y el flag, el panel vuelve
- ✅ Aparece un .php en carpeta pública -> modo bloqueo
- ✅ Página pública alterada: el panel sigue disponible (se puede restaurar)
- ✅ Login tras las pruebas
