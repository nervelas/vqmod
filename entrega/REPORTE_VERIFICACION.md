# Reporte de verificación · Agenda Premium 1.0.0

Fecha de la verificación: 04/10/2026 · Entregable: `agenda-premium.zip` (1,1 MB, 235 archivos PHP, se descomprime directo en la raíz del hosting).

## Resumen
| Área | Resultado |
|---|---|
| Pruebas automatizadas propias | **2 640 comprobaciones correctas, 0 fallidas** en 35 archivos (`php tests/run.php`) |
| Sintaxis | `php -l` con 8.3.6 en todo el ZIP · análisis de sintaxis con **PHP 8.0.30 real** (WebAssembly) en los 235 archivos: 0 errores · revisión de funciones llamadas contra el runtime 8.0: solo quedan funciones protegidas con `function_exists` o del plugin de WordPress |
| Instalación desde cero (web) | OK, también **desde el ZIP descomprimido en una subcarpeta** (`/agenda`); instalador se bloquea; las migraciones nuevas se aplican solas |
| Navegador (Chromium headless) | 126 recorridos (6 públicas + 36 del panel × 360/768/1440 px): 0 desbordes horizontales, 0 errores de consola, 0 recursos externos, 0 respuestas 4xx/5xx propias |
| Accesibilidad | axe-core (WCAG 2.0/2.1 A y AA) en 10 pantallas oscuras, 4 claras y 4 móviles: **0 violaciones** (se corrigió 1 antes de entregar) |
| Auditoría legal técnica | 0 coincidencias de marcas competidoras en todo el proyecto; licencias en `LICENCIAS.txt` y `CREDITOS.txt` |

## 1. Sintaxis y compatibilidad PHP 8.0
- `php -l` (8.3.6) sobre todos los `.php` del ZIP y verificación de sintaxis de los JS (`new Function`).
- PHP **8.0.30** ejecutado vía `@php-wasm/node`: `token_get_all(..., TOKEN_PARSE)` de los 235 archivos → 0 errores. Se comprobó que el detector sí falla con `enum`, `readonly` y `foo(...)`.
- Búsqueda de funciones 8.1+ (`array_is_list`, `enum_exists`, `fsync`, `mb_str_pad`, `json_validate`…): sin coincidencias. Todas las llamadas a funciones no definidas en 8.0 están protegidas (`fastcgi_finish_request`, `idn_to_ascii`) o pertenecen al plugin de WordPress.
- **Limitación:** las pruebas de ejecución se corrieron en PHP 8.3.6; con 8.0 solo se verificó sintaxis y existencia de funciones.

## 2. Entorno de pruebas
MariaDB 10.11.14 local y servidor embebido de PHP. **No se probó con MySQL 5.7 real** (el SQL usa solo sintaxis compatible: sin JSON nativo, sin `DEFAULT (expr)`). No se probó con Apache/LiteSpeed/Nginx reales: el `.htaccess` y los bloques Nginx documentados no se ejecutaron (el servidor embebido replica el bloqueo de carpetas).

## 3. Instalación, bloqueo y migraciones
`tests/cases/inst_web_test.php` (27): requisitos, CSRF faltante → 419, contraseña de BD incorrecta con mensaje amable, contraseña de administrador débil rechazada, instalación completa con profesión Dentista + datos demo, configuración escrita con permisos 640, instalador bloqueado (GET y POST), panel y página pública funcionando, migraciones registradas, y una migración nueva (`999_…`) se aplica sola al abrir el sitio. Repetido contra el ZIP descomprimido en `/agenda` con la ruta de configuración por defecto.

## 4. Pruebas automatizadas por tema
| Tema | Archivos de prueba | Comprobaciones |
|---|---|---|
| Núcleo, zonas horarias, feriados | core_test | 15 |
| Disponibilidad y reservas (individual, grupal con cupos, round robin equitativo/ponderado/prioridad con 99 y 60 reservas, colectivo, con recurso, serie, enlace de un solo uso, aprobación manual, buffers, límite diario, aviso mínimo, anticipación máxima, feriados incl. Semana Santa, excepciones, ausencias, validaciones, inyección SQL/XSS) | core_availability | 62 |
| **Concurrencia**: 50 procesos reservando el mismo horario → **exactamente 1**; 50 procesos sobre sesión grupal de 5 cupos → **exactamente 5** (nunca se exceden); round robin concurrente sin duplicar anfitrión | core_concurrency | 7 |
| Zonas horarias con cambio de horario (America/New_York, Europe/Madrid, America/Guatemala) | core_test, core_availability | 8 (dentro de las anteriores) |
| Importación ICS (RRULE, EXDATE, TZID, todo el día), 304/ETag, backoff, SSRF | sa_ics, sa_extcal, sa_safehttp | 76 + 41 + 152 |
| SSRF: ~50 IP/notaciones privadas (decimal, hex, octal, IPv4 mapeada, metadatos), `file://`, `gopher://`, redirecciones, límite de tamaño y tiempo | sa_safehttp | (incluido) |
| Correo contra servidor SMTP local (AUTH LOGIN/PLAIN, STARTTLS, SSL, UTF-8, adjuntos, cola con reintentos, respaldo a `mail()`) | sa_smtp | 63 |
| Flujos de trabajo (variables por zona del invitado, reintentos 2 fallos + éxito, 3 fallos → failed, `cancelPending`, integración reserva → correo en cola → enviado) | sa_workflow | 83 |
| Webhooks con firma HMAC-SHA256 verificada en receptor local (y firma alterada rechazada), reintentos | sa_webhook | 58 |
| Cron por CLI y por URL (token válido/inválido), bloqueo simultáneo, limpieza | sa_cron | 53 |
| Precios, cupones, certificados, paquetes (carreras con 10 procesos), pagos, lista de espera, enrutamiento con bitácora, analítica/CSV, encuesta de horarios de punta a punta | sb1_* (6 archivos) | 267 |
| Profesiones (15), legal, exportar/eliminar datos de una persona, retención, respaldo/restauración, exportar/importar configuración | sb2_profession/legal/backup/config | 632 |
| API v1 por HTTP: clave válida, inválida, revocada, solo lectura intentando escribir (403), límite de frecuencia (429), paginación, inyecciones | sb2_api | 224 |
| Acceso al panel: login, bloqueo por intentos, 2FA, recuperación de contraseña (un solo uso, caducidad), roles, IDOR, CSRF (419), XSS/SQLi, CSV, fusión de clientes | a1_* | 239 |
| Eventos, anfitriones, usuarios, horarios, ausencias, feriados, calendarios (IDOR, subida maliciosa .php/doble extensión/MIME falso/SVG rechazada) | a2_* | 199 |
| Ventas, flujos, enrutamiento, encuestas, webhooks, claves de API, mensajes, pagos | a3_admin | 134 |
| Ajustes, legal, secretos cifrados, respaldo, importación, estado del sistema, correo de prueba | a4_* | 168 |
| Página pública: CSRF público, honeypot, tiempo mínimo, límite de frecuencia, XSS, reserva completa por HTTP, gestión (reprogramar/cancelar/.ics/recibo), comprobante, captcha (lógica de servidor), embebido, plugin de WordPress (simulacro) | pub_* | 140 |

## 5. Navegador y accesibilidad
- **Barrido integrado** (instalación con profesión Dentista + demo): `/`, `/e/{slug}`, `/equipo`, `/privacidad`, `/terminos`, `/api-docs` y las 36 pantallas del panel a 360, 768 y 1440 px: sin desbordes, sin errores de consola, sin peticiones externas, sin imágenes rotas (las únicas `<img>` sin cargar son marcadores ocultos de vista previa sin `src`).
- **Subcarpeta**: portada, reserva, panel, calendario, eventos e insertar bajo `/agenda`: 0 errores 4xx, 0 externos, 0 errores de consola.
- **axe-core** WCAG 2.1 A/AA: 0 violaciones tras corregir una (`aria-label` sin rol en los contadores animados).
- **Contraste** medido por el agente de diseño (modo oscuro / claro): texto 17,31 / 15,99; secundario 9,19 / 6,98; oro sobre fondo 8,23 / 5,82; botón dorado ≥ 4,91; foco 14,44 / 8,86 (todo ≥ AA).
- **Teclado y `prefers-reduced-motion`:** esfera de reloj y lista operables solo con teclado (flechas, Enter, Espacio; etiqueta «11:30 a. m., disponible»); flujo completo con movimiento reducido sin errores; modal, confirmaciones, pestañas, menú y ordenar arrastrando con teclado (37 comprobaciones de `ui.js`).
- **Peso:** página de reserva medida en 372 KB (fuentes, JS y CSS incluidos) por el agente público; es una sola medición con Chromium sobre la página de reserva de la demo. Páginas públicas **sin cookies** (0 cookies).
- **PWA:** manifiesto válido, tres iconos PNG existentes (192, 512, maskable), `sw.js` con manejador `fetch` y registro desde el panel. No se probó el aviso real de instalación del navegador.

## 6. Auditoría legal técnica
- Búsqueda insensible a mayúsculas de ~35 nombres de productos competidores (agendamiento, reservas y encuestas de fechas) en todo el proyecto: **0 coincidencias** (se renombró una variable JS que coincidía por casualidad y se quitaron nombres de aplicaciones de autenticación del texto de ayuda). Las únicas marcas que aparecen son las permitidas como nombres técnicos (Google Calendar, Outlook, Apple/.ics, WhatsApp, Zapier, Make, Jitsi) con aviso de no afiliación en `LICENCIAS.txt`.
- Recursos de terceros incluidos: tres fuentes OFL (Fraunces, Manrope, Space Mono) y `qrcode-generator` 2.0.4 (MIT); licencias completas en `LICENCIAS.txt`, tabla en `CREDITOS.txt`. Iconos, guilloché, escenas, emblema y todo el diseño son originales.
- **Limitación:** no se puede demostrar mediante una herramienta que ningún texto o forma se parezca a los de terceros; se afirma solo que todo fue escrito/dibujado desde cero para este proyecto y no se copió código, texto ni imágenes.
- **Fotografías:** Unsplash/Pexels no son accesibles desde este entorno (el proxy rechazó la conexión). Se sustituyeron por 10 composiciones SVG originales (`assets/img/scenes/`) y el administrador puede subir sus propias imágenes. **Declarado: no se incluye ninguna fotografía real.**

## 7. Revisión de archivos entregados
- El ZIP no contiene `tests/`, registros, sesiones, caché, `config/config.php`, credenciales de prueba ni rutas del entorno de desarrollo (búsqueda de `ap_test_pw`, `/home/user`, `Demo#`, `claude`: 0 coincidencias). El esqueleto `storage/` va vacío y bloqueado.
- Auditoría de SQL: las consultas con variables interpoladas usan solo enteros convertidos, nombres de columnas de una lista fija o listas de marcadores `?`.

## 8. Lo que NO se pudo probar (límites reales)
1. Servicios externos reales: proveedores de correo, **WhatsApp Cloud API** (solo contra un receptor local falso), **Turnstile/hCaptcha** (solo lógica de servidor, CSP y rechazo sin token), **Zapier/Make**, calendarios reales de Google/Outlook/Apple (solo servidores ICS locales).
2. MySQL 5.7, Apache/LiteSpeed/Nginx reales, HTTPS/HSTS en un servidor real, dispositivos Android reales, PHP 8.0 en ejecución (sí en sintaxis), IPv6 con fijado de IP (el entorno no tiene IPv6).
3. WordPress real: el plugin se probó con funciones simuladas.
4. Arrastrar citas con el dedo en pantalla táctil (se usa el botón «Reprogramar»); la esfera acepta toque simple, el arrastre continuo es con ratón/lápiz.
5. Directorios sensibles: están **dentro** de la carpeta del sitio y bloqueados con `.htaccess`; no se implementó mover `storage/` fuera del webroot.
6. El mapa del negocio es solo un enlace (sin iframe, para no cargar recursos externos).
7. Los textos legales y las plantillas de profesión son genéricos: deben ser revisados por un abogado y por el negocio.
8. Notas de comportamiento: una oferta de lista de espera no reserva el horario durante sus 15 min (otra persona puede tomarlo y la oferta falla con aviso); la conversión por fuente en analítica puede superar 100 % cuando hay citas sin visita registrada en esa fuente; los webhooks se entregan en la siguiente pasada del cron; el cron exacto requiere programarlo (hay respaldo por visitas).
