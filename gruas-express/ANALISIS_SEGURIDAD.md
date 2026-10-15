# ANÁLISIS DE SEGURIDAD Y ARQUITECTURA — Grúas Express Towing

## 1. Problema

El sitio actual (WordPress + Elementor + OceanWP + 3 plugins) **está infectado hoy**: dos de sus cuatro páginas sirven spam de apuestas, el `sitemap.xml` fue reemplazado por uno falso y una página real devuelve 404 (ver `INVENTARIO.md §0`). En el mismo hosting hay dos sitios HTML estáticos hechos con Xara que **nunca se han infectado**. Objetivo: igualar el nivel de seguridad de Xara en la parte pública, sin perder un panel para administrar el contenido ni el formulario.

## 2. Opciones evaluadas

| Criterio | **A. WordPress** | **B. HTML estático puro (modelo Xara)** | **C. Estático + panel que genera páginas + 1 script PHP de formulario** ✅ | **D. PHP dinámico + BD en cada visita** | **E. Otras** (ver §2.1) |
|---|---|---|---|---|---|
| Código ejecutándose por visita pública | Todo el núcleo + plugins + tema (cientos de miles de líneas de terceros) | **Ninguno** | **Ninguno** (el visitante recibe archivos) | Todo el CMS propio, con BD | Depende |
| Superficie de ataque pública | Muy alta: `wp-login`, `xmlrpc.php`, REST API, ~60 rutas de plugin, subidas ejecutables, ataques a la cadena de suministro (plugins/temas) | **Mínima**: servidor web + credenciales FTP/cPanel | **Mínima en lo público**; adicional y acotada: `/admin/` y `/enviar.php` | Alta: SQLi/XSS/RCE llegan a todos los visitantes | Varía |
| Historial de infecciones en hosting compartido | **El más alto** (miles de sitios/semana; este mismo sitio) | **Prácticamente nulo** a nivel de sitio; los casos conocidos vienen de credenciales FTP robadas o de otra cuenta infectada en el mismo servidor | Igual que B en lo público; el riesgo añadido está limitado a 2 puntos blindados | Medio (depende del código; CMS caseros mal hechos son comunes) | Varía |
| Compatibilidad Apache/LiteSpeed + PHP ≥ 8.0 | Requiere MySQL, `mod_rewrite`, permisos de escritura amplios | Total (solo estáticos) | **Total**: PDO-SQLite, GD, mbstring, fileinfo (estándar), sin Composer ni extensiones raras | Requiere BD y escritura | Varía |
| Facilidad de administración | Alta, pero exige actualizar sin parar | Baja: programa de escritorio + re-subir por FTP | **Alta**: panel web + botón «Publicar» | Alta | Varía |
| Formulario de contacto | Plugin de terceros (Forminator, otra superficie) | No tiene (solo `mailto:` o servicio externo) | **1 script PHP mínimo, blindado** | En el CMS | Servicio externo |
| Rendimiento | Medio/bajo sin caché | **Máximo** | **Máximo** (estático) | Medio | Varía |
| ¿Cumple TODOS los requisitos? | No (infección constante) | No (sin panel ni formulario propio) | **Sí** | No (rompe la paridad con Xara) | No / parcialmente |

### 2.1 «E. Otras opciones viables en hosting compartido»

| Opción | Veredicto |
|---|---|
| E1. Constructores SaaS (Wix, Squarespace…) | Seguros, pero **no es plataforma propia**, no conservan URLs ni contenido exacto, cargan recursos de terceros y hay dependencia del proveedor. Descartada. |
| E2. Generador estático local (Hugo/Jekyll/Eleventy) + FTP | Muy seguro, pero **sin panel**: cada cambio exige un desarrollador y herramientas de compilación. Descartada por el requisito de administración. |
| E3. CMS headless + compilación en Node/CI | Excelente, pero necesita Node/CI fuera del hosting compartido. Descartada. |
| E4. CMS de archivos planos (Grav, Kirby…) | PHP + código de terceros ejecutándose en cada visita: vuelve a la superficie de A/D. Descartada. |
| E5. Netlify/Cloudflare Pages + formularios externos | Muy seguro, pero fuera del hosting actual y con recursos/servicios de terceros. Descartada. |

## 3. Decisión: **opción C** y razones

1. **El visitante nunca ejecuta PHP.** Lo que se publica son `.html`, `.css`, `.js`, imágenes y fuentes (igual que Xara). Por eso una vulnerabilidad en el panel no cambia lo que ve el público, y no hay nada que inyectar «en vivo».
2. **PHP queda confinado a exactamente 2 puntos de entrada:** `admin/index.php` y `enviar.php`. Todo otro `.php` (en uploads, assets, páginas, raíz o `_core`) devuelve **403** por `.htaccess`; el código de la aplicación vive en `_core/` (acceso web denegado) y cada archivo comienza con un guardián que no imprime nada si se invoca directamente.
3. **Lo único que se puede escribir son datos y salidas:** `_core/data/` (BD SQLite con nombre aleatorio, logs, sesiones, respaldos), `uploads/` y los archivos generados. **Los archivos PHP del núcleo nunca los escribe la aplicación ni se editan desde el panel** (solo contenido; HTML permitido con lista blanca estricta).
4. **Defensa en profundidad en las 2 puertas:** contraseña HTTP del servidor (`.htpasswd`, hash apr1) → login con Argon2id/bcrypt, bloqueo por IP y usuario, errores genéricos → 2FA TOTP propio → restricción opcional por IP → ruta de admin renombrable → CSRF, sesiones `HttpOnly`/`SameSite=Strict`/`Secure`, expiración por inactividad, registro de auditoría.
5. **Detección y contención, algo que Xara no tiene:** monitor de integridad SHA-256 + **modo bloqueo** (si cambia un archivo de código o aparece PHP no autorizado, panel y formulario se apagan, el sitio estático sigue sirviéndose y llega una alerta por correo), escáner de malware, respaldos automáticos con rotación.
6. **El formulario es un único script de ~150 líneas:** token HMAC de un solo uso y con edad mínima/máxima, honeypot, límite por IP (6/h, 20/día), validación estricta, anti-inyección de cabeceras, comprobación de origen, consultas preparadas. Además, el envío abre el **WhatsApp** de la empresa (requisito del cliente), de modo que el contacto funciona aunque PHP o el correo fallen.

## 4. Tabla «Qué tiene Xara vs. qué tiene este sitio»

| Aspecto | Xara (sitios estáticos) | Este sitio | Diferencia y mitigación |
|---|---|---|---|
| PHP/código en el sitio público | Ninguno | **Ninguno** (PHP bloqueado en todo lo público) | Sin diferencia para el visitante |
| Archivos `.php` desplegados | 0 | 2 puntos de entrada + 16 librerías en `_core/` (denegado por web) | **Diferencia real.** Mitigación: 403 a cualquier otro `.php`; guardián en cada archivo; permisos 444 recomendados; monitor de integridad con modo bloqueo |
| Base de datos | No | SQLite con nombre aleatorio en `_core/data/` | Bloqueada por `.htaccess` (403) y no listable; sin credenciales de BD que robar |
| Subida de archivos | No | Solo imágenes, solo desde el panel autenticado | Extensión + MIME real + firma + límites; **recodificación GD a WEBP** (destruye código incrustado); nombre aleatorio; carpeta sin ejecución y con CSP `sandbox`; alerta si aparece algo que no sea `.webp` |
| Formulario de contacto | No tiene o usa servicio externo | `enviar.php` | Superficie añadida mínima y blindada (ver §3.6) |
| Panel de administración | No (se edita en el PC y se sube por FTP) | `/admin/` | 4 capas (Basic Auth, login con bloqueo, 2FA, IP) + ruta renombrable + noindex + CSP sin inline |
| Cómo se publica un cambio | Re-subir por FTP desde el PC | Botón **Publicar** regenera los estáticos | Salidas en rutas fijas; la app no puede crear PHP |
| Detección de alteraciones | Ninguna | SHA-256 de todo el código + manifiesto de páginas generadas + escáner | **Mejora sobre Xara**: Xara no avisa si alguien cambia un `.html` |
| Respaldos | Los del PC/hosting | BD diaria con rotación (14) + ZIP completo descargable | Mejora |
| Credenciales que pueden filtrarse | FTP/cPanel | FTP/cPanel + panel (hashes fuertes) | Contraseñas aleatorias de 20 caracteres, cambio obligatorio, 2FA |
| Recursos de terceros | Normalmente ninguno | **Ninguno** (CSS, JS, fuentes e íconos locales) | Sin diferencia; CSP `default-src 'self'` sin `unsafe-inline`/`unsafe-eval` |
| Rendimiento | Estático | Estático (+ CSS/JS minificado, WEBP, lazy-load) | Sin diferencia |

## 5. Riesgos residuales (honestos) y cómo se cubren

| Riesgo | Alcance | Mitigación |
|---|---|---|
| Robo de credenciales FTP/cPanel (causa más común de infecciones también en Xara) | Cualquier opción | Contraseñas únicas y fuertes, 2FA del hosting, SFTP. Si alguien modifica un archivo por FTP, el monitor lo detecta en la siguiente visita al panel/formulario y envía correo |
| Otra cuenta infectada en el mismo servidor compartido | Cualquier opción | Pedir aislamiento (CageFS/suEXEC) al hosting; permisos 755/644 y 444 para el núcleo |
| `.htaccess` ignorado (p. ej. nginx puro) | Afecta a C | Las librerías no hacen nada si se invocan directo (guardián); la BD tiene nombre aleatorio. En nginx hay que replicar las reglas (documentado en el README) |
| `php_flag engine off` solo existe en `mod_php`; en LiteSpeed/PHP-FPM no aplica | Parcial | En esos casos rige el bloqueo `FilesMatch` (403) + `.user.ini`. Verificado en Apache + `mod_php` |
| `disable_functions`, `allow_url_fopen`, `expose_php` solo se pueden fijar a nivel servidor | Parcial | Están en `.user.ini` y el README pide fijarlas en cPanel; el panel muestra si están activas. **El código no usa ninguna de esas funciones** (auditado con grep) |
| `TRACE` y la cabecera `Server:` son directivas de servidor | Menor | `TraceEnable off` y `ServerTokens Prod` a pedir al hosting |
| `mail()` sin sendmail/SMTP | Funcional | Opción SMTP en el panel; además la copia queda en la BD y el WhatsApp siempre se abre |
| Sin QR para el 2FA | Usabilidad | Se muestra la clave manual (compatible con Google/Microsoft Authenticator y Authy) |

## 6. Controles implementados (resumen)

- **Servidor:** `.htaccess` (PHP solo en 2 archivos, bloqueo de BD/logs/config/backups/ocultos/docs, métodos GET/POST/HEAD, filtro de SQLi/XSS/traversal/UA maliciosos, CSP estricta, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP/CORP, HSTS, HTTPS y no-www, 301 de URLs WordPress, errores propios); `.htaccess` por carpeta pública (sin scripts, sin listado, solo tipos esperados); `.user.ini`.
- **Código:** sin `eval`, `assert` con cadenas, `create_function`, `preg_replace /e`, `base64_decode`, `unserialize`, `extract`, variables variables ni `include` dinámico; 100 % consultas preparadas PDO; escape de salida en todo; validación estricta de cada entrada; sin llamadas a comandos del sistema.
- **Panel:** `password_hash` (Argon2id si existe, si no bcrypt), bloqueo temporal por IP y por usuario, sesiones endurecidas con regeneración de ID, CSRF + comprobación de origen, 2FA TOTP (RFC 6238 verificado) con anti-reutilización, registro de accesos/fallos/cambios visible en el panel, HTML con lista blanca idempotente, CSV a prueba de inyección de fórmulas.
- **Anti-infección:** línea base SHA-256, modo bloqueo, escáner (`eval`/base64/gzinflate/`str_rot13`/comandos/iframes ocultos/scripts externos/`.htaccess` sospechosos/código en imágenes), recuperación documentada (restaurar + borrar `lockdown.flag` y `baseline.json`).
