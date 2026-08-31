# INFORME — Limpieza de malware y rediseño de agroinco.com
**Fecha:** 31 de agosto de 2026 · **Entorno de trabajo:** WordPress 7.0.4 · PHP 8.4 · MariaDB local

---

## 1. Malware encontrado y eliminado

| Hallazgo | Ubicación | Acción |
|---|---|---|
| **1,376 entradas de blog de spam de casinos** (multiidioma: polaco, checo, portugués, ruso, azerí; "Spinzen", "Vavada", "Mostbet", "Potter Slots"…) creadas de 2023 a 2026 | `wp_posts` (blog completo — no existía ni un artículo legítimo) + 666 revisiones + 8,583 metadatos | Eliminadas por completo (2,042 registros + metadatos) |
| **Enlaces ocultos de casino** con `position:fixed; left:9166px` — "spino gambino" (spino-gambino.de/.com.pt), "vegas hero" (vegashero.pt), "duo spin" (duospin.ch), "spinmama" (spinmamas.com) | Página INICIO (contenido y datos de Elementor) | Extirpados quirúrgicamente; el texto legítimo quedó íntegro (el anchor partía la palabra "inciden") |
| Enlace sueco oculto `eggsinc.se` y enlace camuflado `ghostwriter.com.de` (color idéntico al texto) | INICIO y página "Hules industriales" | Eliminados; redacción restaurada sin huecos |
| **9 cuentas de administrador falsas** (admbqy66e, admc14km3, adml8t7rp, jisuhlas, admfbhbq7, bot, admpg05ta, dev_f823e4, admin_d80697; creadas mar–abr 2026) | `wp_users` | Eliminadas con sus metadatos |
| **3 tareas cron del atacante** (`fgyzhnwogs6p5sbaizz`, `oe3jp_zmoub_0r6l52_nsq`, `mnx_daily_cron_event`) — el mecanismo que publicaba el spam | Opción `cron` | Eliminadas (+ crons huérfanos de Jetpack/Duplicator) |
| **Elementor Pro 3.21.3 PIRATA** y **Rank Math Pro PIRATA** (cuenta falsa "user420@gmail.com") — vector de infección más probable | `wp-content/plugins/` | Eliminados. El único widget Pro en uso (nav-menu, 13 páginas) se sustituyó por shortcode propio en el tema hijo; los datos estructurados se verificaron idénticos con Rank Math gratuito |
| Carpeta residual del atacante `xenon-collector-kit` | plugins | Eliminada |
| Log de IndexNow con las URLs spam notificadas a buscadores | `wp_options` | Eliminado |
| Núcleo WP y tema OceanWP | — | **Verificados byte a byte contra los oficiales: limpios.** Núcleo reemplazado igualmente por WordPress 7.0.4 oficial |

**Escaneo final: 0 detecciones** (PHP en uploads, eval/base64 ofuscado, firmas de webshell, spam en BD, usuarios no autorizados, permisos 777). También se eliminaron plugins abandonados/duplicados (MalCare, Easy Updates Manager, Duplicator, ElementsKit, Happy Addons, Premium Addons — este último causaba un error fatal recurrente) y el tema inactivo Twenty Twenty-Five.

## 2. Usuarios y contraseñas
- **Único administrador:** `Admin-SMS` (nervelas@gmail.com) · **Contraseña nueva:** `Agr01nc0!Sellos#2026$Gt` — cámbiala tras el primer ingreso. Todas las sesiones fueron invalidadas (salts nuevos).
- URL de acceso: se **conservó** `/wp-login.php` (cambiarla requería un plugin extra); Wordfence bloquea fuerza bruta: 5 intentos → bloqueo de 4 h, usuarios inexistentes bloqueados, enumeración de autores desactivada.

## 3. Rediseño "Industrial de élite" (solo capa visual)
- Tema hijo `oceanwp-child` propio: paleta técnica (#F5F6F4 / grafito #20242A / acero #5A6470 / naranja #E8590C), tipografía **Space Grotesk + Inter auto-alojadas**, textura blueprint sutil, header que se compacta al bajar, animaciones sobrias con IntersectionObserver (respetan `prefers-reduced-motion`).
- INICIO como landing: accesos con iconos SVG a las **9 categorías**, sección **"desde 1976"** con contadores animados, marcas (**John Crane, Klinger**), industrias atendidas, proceso de cotización y CTAs directos (tel: PBX 2506 8100 · wa.me 4076 9228 · mailto).
- Catálogo: tarjetas técnicas con foto protagonista, código visible y botón naranja **"Cotizar por WhatsApp"** por producto (mensaje prellenado con nombre y código); botón WhatsApp flotante en todo el sitio.
- Rendimiento: **1,593 imágenes convertidas a WebP** (−187 MB; nombres y ALT intactos, servidas por reescritura transparente), CSS/JS minificados y podados (−320 KB de fuentes/estilos sin uso), caché de página propia sin plugins.
- **Lighthouse móvil (3 corridas estables): Rendimiento 80 · Accesibilidad 92 · Buenas Prácticas 100 · SEO 100.** Responsive verificado en 360/768/1024/1440 px sin desbordes; menú móvil y botones táctiles ≥48 px. Consola JS y log PHP: sin errores en PHP 8.4.

## 4. Contenido y SEO intactos
**225/225 campos idénticos** (meta title, meta description y H1 de las 75 URLs legítimas: 21 páginas + 45 productos + 9 categorías) — ver `TABLA-SEO-VERIFICACION.md`. Mismos permalinks, canonicals, ALT de imágenes y datos estructurados (Organization, WebSite, WebPage, ImageObject, Person, Article). Ni una palabra de contenido reescrita; lo único removido es el spam.

## 5. Blindaje aplicado
`wp-config.php` regenerado (salts nuevos, `DISALLOW_FILE_EDIT`, `FORCE_SSL_ADMIN`, debug off, actualizaciones menores automáticas) · `.htaccess` endurecido (HTTPS forzado, XML-RPC bloqueado, sin listado de directorios, bloqueo de .sql/.log/.zip, cabeceras X-Frame-Options/nosniff/Referrer-Policy/HSTS/Permissions-Policy, wp-config protegido) · PHP bloqueado en `/uploads/` · **Wordfence** activo y configurado (firewall, escaneo programado, límite de intentos, alertas a info@servicom.gt) · **BackWPup** instalado para respaldos programados.

## 6. CHECKLIST — qué debes hacer TÚ al subirlo
1. **Respalda lo actual** del hosting (aunque esté infectado) antes de reemplazar.
2. Sube y descomprime `agroinco-limpio.zip` en `public_html` (reemplazando todo) e importa `agroinco-bd-limpia.sql` en la BD `servicom001_AgroInco` (mismo nombre/usuario/prefijo — funciona de inmediato).
3. **Cambia YA las contraseñas de cPanel, FTP y del usuario MySQL** (si cambias la de MySQL, actualízala en wp-config.php). El atacante pudo haberlas capturado.
4. Entra a wp-admin con la contraseña nueva y cámbiala; en **Wordfence** ejecuta un primer escaneo y activa el firewall en modo "optimizado"; en **BackWPup** crea el trabajo programado (BD + archivos, semanal, guardado externo).
5. **Actualiza plugins y WordPress** desde el panel (aquí no había salida a wordpress.org).
6. En **Google Search Console**: usa "Inspección de URLs" y solicita revisión si hay acción manual/aviso de seguridad; sube de nuevo el sitemap de Rank Math (las URLs spam ahora devuelven 404, que es lo correcto).
7. Purga cachés (hosting/CDN/Cloudflare si aplica) y verifica el sitio en modo incógnito.
8. No reinstales NUNCA plugins piratas ("nulled"): fueron la puerta de entrada.
