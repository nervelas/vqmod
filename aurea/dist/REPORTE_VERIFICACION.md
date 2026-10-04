# REPORTE DE VERIFICACIÓN — AUREA Agenda Profesional Premium v1.0.0

Fecha de la verificación: 04/10/2026. Entorno: Linux, PHP 8.3.6 (CLI + servidor embebido con 12 trabajadores), MariaDB 10.11, Chromium headless (Playwright), servidor SMTP y API simulados en local.

**Resultado global de la suite automatizada: 232/232 pruebas pasan, 0 fallan** (ejecutada 4 veces consecutivas sobre el ZIP descomprimido; sin intermitencias). Durante las pruebas se encontraron y corrigieron defectos reales (ver sección 9).

## 1. Sintaxis y compatibilidad PHP 8.0

| Verificación | Resultado |
|---|---|
| `php -l` en todos los .php con PHP 8.3 | Sin errores |
| **PHP 8.0.30 real** (WebAssembly `@php-wasm/node-8-0`): parser oficial (`token_get_all` con `TOKEN_PARSE`) sobre los 109 archivos PHP | **0 errores de sintaxis** |
| PHP 8.0.30: carga/compilación de las 54 clases (Core, Services, Controllers) | 54 cargadas, 0 fallos |
| PHP 8.0.30: lógica pura (Pascua 2026/2027, teléfonos GT, slug, TOTP vector RFC 6238) | OK |
| Búsqueda de sintaxis/funciones 8.1+ (enum, readonly, never, `(...)`, `array_is_list`, `new` en inicializadores) | Ninguna encontrada |

## 2. Pruebas automatizadas (tests/run.php, no incluidas en el ZIP)

Instala desde cero por HTTP contra MariaDB real y ejecuta peticiones reales (cURL), procesos CLI y concurrencia real (`curl_multi` contra 12 trabajadores PHP).

### Instalador (11/11)

- ✅ Sin instalar, la raíz redirige al instalador
- ✅ Paso 1: requisitos se muestran y permiten continuar
- ✅ Instalador rechaza POST sin token CSRF
- ✅ Paso 2: credenciales de BD inválidas muestran error amigable
- ✅ Paso 2: conexión correcta avanza al paso 3
- ✅ Paso 3: contraseña débil rechazada
- ✅ Paso 3: datos válidos avanzan al paso 4
- ✅ Paso 4: instalación completa y se anuncia el bloqueo
- ✅ Se generó config/config.php con clave secreta y sin la contraseña de admin
- ✅ Instalador bloqueado automáticamente tras instalar (403)
- ✅ POST al instalador bloqueado también es rechazado

### Reserva pública: flujo completo (15/15)

- ✅ Página /reservar responde 200 con datos de arranque
- ✅ API días: devuelve días disponibles y próximo horario
- ✅ API horarios: devuelve chips de horarios
- ✅ Reserva completa de 4 pasos (con formulario condicional) → OK
- ✅ La cita quedó confirmada con precio y respuestas guardadas
- ✅ Campo condicional visible y obligatorio se valida en servidor
- ✅ Campo condicional oculto no es obligatorio
- ✅ Teléfono inválido (no 8 dígitos) rechazado
- ✅ Correo inválido rechazado
- ✅ Sin consentimiento de privacidad no se permite reservar
- ✅ Fecha en el pasado rechazada
- ✅ Hora fuera del horario laboral rechazada
- ✅ Fecha con formato inválido rechazada
- ✅ El consentimiento de privacidad quedó registrado (fecha, IP)
- ✅ Mismo teléfono reutiliza la ficha del cliente (sin duplicar)

### Gestión por token: confirmar / cancelar / reprogramar (11/11)

- ✅ Página de la cita (éxito) con botones .ics, Google, WhatsApp
- ✅ Token inexistente → 404
- ✅ Confirmar asistencia por token (con CSRF)
- ✅ Confirmar sin token CSRF → 419
- ✅ Formulario de reprogramación disponible
- ✅ Reprogramar por token crea nueva cita y marca la anterior "reprogramada"
- ✅ El enlace antiguo redirige a la cita nueva
- ✅ Historial de la cita original registra la reprogramación
- ✅ Cancelar por token respetando la política (>12 h)
- ✅ Política de cancelación: <12 h no permite cancelar en línea
- ✅ Política: tampoco permite reprogramar con tan poca anticipación

### Disponibilidad: feriados, buffers, aviso, anticipación, grupos (27/27)

- ✅ Pascua 2025 = 20/abr, 2026 = 5/abr, 2027 = 28/mar
- ✅ Semana Santa 2027 calculada (Jueves 25/mar, Viernes 26, Sábado 27)
- ✅ Feriados fijos de Guatemala precargados (1 ene, 1 may, 30 jun, 15 sep, 20 oct, 1 nov, 25 dic)
- ✅ 15 de agosto marcado "solo ciudad de Guatemala" y desactivable
- ✅ Feriado de día completo (25/dic/2027) no ofrece horarios
- ✅ Jueves Santo 2027 (25/mar) no ofrece horarios
- ✅ 24/dic: medio día (último horario termina a las 12:00)
- ✅ 31/dic/2027: medio día
- ✅ Fecha laboral normal sí ofrece horarios (mañana y tarde, con pausa)
- ✅ Sábado solo horario de mañana; domingo sin horarios
- ✅ 15/ago (feriado ciudad) bloquea; al desactivarlo se atiende
- ✅ Feriado desactivado deja de bloquear (domingo con horario de prueba)
- ✅ Vacaciones/ausencia (rango) bloquean al profesional pero no a otros
- ✅ Ausencia parcial general (todos) bloquea solo ese rango
- ✅ Cita con buffer posterior de 15 min bloquea hasta las 10:45
- ✅ Buffer: horario previo cuyo bloque choca (9:30 → 10:15) no se ofrece; 9:15 sí
- ✅ Reservar dentro del buffer es rechazado
- ✅ Aviso mínimo 48 h: mañana sin horarios
- ✅ Aviso mínimo: pasados 48 h sí hay horarios (dentro de 5 días)
- ✅ Anticipación máxima 5 días: a 7 días no hay horarios
- ✅ Reserva con menos aviso del mínimo es rechazada en servidor
- ✅ Reserva más allá de la anticipación máxima es rechazada
- ✅ Clase grupal: 3 cupos ocupados, el 4.º es rechazado
- ✅ Clase llena deja de ofrecerse
- ✅ Otro servicio que se traslapa con la clase grupal es rechazado
- ✅ El mismo horario en otro profesional sigue disponible
- ✅ Clase grupal: el horario muestra los cupos restantes (2 de 3)

### Concurrencia: 50 reservas simultáneas al mismo horario (5/5)

- ✅ 50 reservas simultáneas (mismo profesional y horario): exactamente 1 exitosa
- ✅ La BD tiene exactamente 1 cita activa en ese horario
- ✅ "Cualquiera disponible" con 50 simultáneas: 2 exitosas (una por profesional, sin duplicar)
- ✅ Clase grupal de 3 cupos con 20 simultáneas: exactamente 3
- ✅ Reservas simultáneas a horarios traslapados (con buffers): ningún traslape en BD

### Cupones, certificados de regalo y paquetes (16/16)

- ✅ Cupón porcentaje 20% (insensible a mayúsculas): Q500 → Q400
- ✅ Cupón de monto fijo Q100: Q500 → Q400
- ✅ Certificado de regalo Q300 aplica y descuenta saldo a 0
- ✅ Certificado sin saldo rechazado
- ✅ Cupón vencido rechazado
- ✅ Cupón de un solo uso: segundo uso rechazado
- ✅ Cupón restringido a otro servicio rechazado
- ✅ Código de cupón con SQL injection rechazado sin error
- ✅ Cancelar restaura el saldo del certificado de regalo (Q300) y los usos
- ✅ API de cupón: vista previa del descuento
- ✅ Servicio con anticipo 30%: cita pendiente con depósito Q300 y vencimiento
- ✅ Paquete de 2 sesiones: 2 usos OK, el 3.º rechazado; total Q0 y saldo correcto
- ✅ Cancelar una cita con paquete devuelve la sesión al saldo
- ✅ Pago parcial → estado "parcial"; pago completo → "pagado"
- ✅ Estado de pago pasa a "pagado" al completar el total
- ✅ Al cubrirse el anticipo, la cita pendiente se confirma automáticamente

### Lista de espera (6/6)

- ✅ Anotarse en la lista de espera (API)
- ✅ Al cancelarse la cita, el horario se ofrece automáticamente al siguiente
- ✅ La oferta generó mensajes en cola (correo y WhatsApp)
- ✅ Página de oferta muestra el horario y el botón de reservar
- ✅ Aceptar la oferta crea la cita y marca la espera como agendada
- ✅ Oferta vencida pasa a la siguiente persona en la fila

### Seguridad: CSRF, inyección SQL y XSS (26/26)

- ✅ POST público sin token CSRF → 419 (rechazado)
- ✅ POST público con token CSRF falso → 419
- ✅ Token CSRF firmado pero vencido (>6 h) → 419
- ✅ Login de admin sin CSRF → 419
- ✅ Login de administrador correcto → /admin
- ✅ Set-Cookie del panel incluye HttpOnly y SameSite=Lax
- ✅ Acción del panel sin token CSRF → 419
- ✅ Acción del panel con CSRF válido funciona
- ✅ AJAX: el token CSRF por cabecera X-CSRF-Token también es válido
- ✅ XSS en nombre/comentario/respuestas: la reserva se acepta (datos se guardan crudos)
- ✅ SQL injection en el select del formulario → opción inválida (rechazado)
- ✅ SQL injection en nombre/respuesta: guardado literal, tablas intactas
- ✅ XSS neutralizado: ninguna página imprime el HTML malicioso sin escapar
- ✅ El HTML aparece escapado (&lt;script&gt;) en la vista de la cita del panel
- ✅ Búsqueda con SQL injection no devuelve error ni todos los registros
- ✅ XSS en ficha de cliente (nombre/etiquetas/notas) escapado en lista, ficha y formulario
- ✅ XSS en reseñas publicadas escapado en home y página del profesional
- ✅ CSP: script-src 'self' sin unsafe-inline ni unsafe-eval; object-src none
- ✅ Cabeceras: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy
- ✅ Widget /embed: sin X-Frame-Options y con frame-ancestors abierto
- ✅ Ninguna página tiene scripts ni manejadores de eventos en línea
- ✅ Sin recursos externos en las páginas (fuentes, scripts, estilos locales)
- ✅ Archivos sensibles no accesibles por web (config, storage, app, database)
- ✅ Errores no se exponen: ruta rota devuelve página amigable sin rutas del servidor
- ✅ Honeypot: formulario con campo oculto lleno es rechazado
- ✅ Límite de frecuencia en reservas públicas: tras 6 intentos → 429

### Permisos por rol e IDOR (19/19)

- ✅ Profesional A puede ver su propia cita
- ✅ IDOR: Profesional A no puede ver la cita de B (404)
- ✅ IDOR: Profesional A no puede cambiar el estado de la cita de B
- ✅ IDOR: Profesional A no puede reprogramar ni anotar la cita de B
- ✅ IDOR: Profesional A no puede ver el cliente de B
- ✅ IDOR: el listado de clientes de A no incluye clientes de B
- ✅ IDOR: archivos de clientes ajenos no se descargan (404)
- ✅ IDOR: filtro ?prof=B en la agenda de A se ignora
- ✅ IDOR: A no ve la cita de B en la lista de citas ni en búsqueda
- ✅ IDOR: A no puede editar el perfil/horario de B
- ✅ Profesional no accede a configuración, usuarios, sistema, servicios (403)
- ✅ Profesional no puede enviar ajustes por POST (403)
- ✅ Recepción ve agenda, clientes y citas
- ✅ Recepción NO accede a configuración, usuarios, sistema, catálogo (403)
- ✅ Recepción no puede eliminar datos de clientes (solo admin)
- ✅ Sin sesión: /admin y rutas del panel redirigen al login
- ✅ Profesional: reportes limitados a su propia actividad
- ✅ IDOR: el CSV del Profesional A no contiene citas del B
- ✅ Usuario inactivo no puede iniciar sesión

### Subida de archivos maliciosos (9/9)

- ✅ Rechazados: .php, doble extensión, MIME falso, código incrustado, HTML, SVG, .htaccess, >5 MB
- ✅ Ninguna carga maliciosa creó pagos ni archivos
- ✅ Comprobante legítimo (PNG) aceptado y registrado como pendiente
- ✅ Archivo guardado con nombre aleatorio (40 hex), sin extensión, fuera del webroot público
- ✅ El archivo privado no es accesible directo por URL (403)
- ✅ Descarga solo mediante script autorizado, con nosniff y sandbox CSP
- ✅ El profesional dueño de la cita puede descargar; el ajeno no (404)
- ✅ Upload::check rechaza .php y contenido que no coincide con la extensión (unitaria)
- ✅ Imagen pública se re-codifica (se elimina cualquier carga útil) y queda con extensión segura

### Autenticación: bloqueo, 2FA TOTP, recuperación de contraseña (23/23)

- ✅ TOTP: vector RFC 6238 (secreto "12345678901234567890", t=59 → 287082)
- ✅ TOTP: verifica el código actual y rechaza uno incorrecto
- ✅ Contraseñas con hash ARGON2ID (nunca texto plano)
- ✅ Política de contraseña fuerte (10+ caracteres, mayúscula, minúscula, número)
- ✅ 5 intentos fallidos bloquean el login (incluso con la contraseña correcta) → 429
- ✅ El bloqueo es por IP+usuario: otras cuentas siguen funcionando
- ✅ Con 2FA activo, el login pide el código y no da acceso aún
- ✅ Código 2FA incorrecto rechazado
- ✅ Código 2FA correcto concede acceso
- ✅ Perfil: activar 2FA con código incorrecto no lo activa
- ✅ Perfil: activar 2FA con el código TOTP correcto
- ✅ Perfil: desactivar 2FA exige contraseña y código
- ✅ La contraseña SMTP se guarda cifrada en la BD (no en texto plano)
- ✅ Recuperación: respuesta idéntica exista o no la cuenta (sin enumeración)
- ✅ Se envió el correo de recuperación por SMTP con enlace de un solo uso
- ✅ El enlace de recuperación es válido
- ✅ Restablecer con contraseña débil es rechazado
- ✅ Restablecer con contraseña fuerte funciona
- ✅ El enlace es de un solo uso (segundo intento inválido)
- ✅ Se puede iniciar sesión con la nueva contraseña
- ✅ Token de recuperación vencido es inválido
- ✅ Sesión: cierre de sesión invalida el acceso
- ✅ Sesión expira por inactividad (servidor)

### Correo SMTP, cola con reintentos, recordatorios y cron (22/22)

- ✅ Al reservar se encolan confirmación (correo + WhatsApp), aviso interno y recordatorios
- ✅ cron.php por CLI se ejecuta y reporta OK
- ✅ Correo de confirmación entregado al servidor SMTP de prueba con asunto correcto (UTF-8)
- ✅ El cuerpo incluye nombre del cliente, servicio, fecha, hora y enlace de gestión
- ✅ Autenticación AUTH LOGIN con usuario/clave del SMTP configurados
- ✅ Remitente y destinatario correctos
- ✅ Correo MIME multipart (texto + HTML) sin inyección de cabeceras
- ✅ Los mensajes enviados quedan marcados "sent" y no se duplican
- ✅ Ejecutar el cron varias veces no reenvía correos ya enviados
- ✅ Si el SMTP falla, el mensaje queda en cola con intento registrado y error
- ✅ Reintento posterior exitoso: el correo se entrega y queda "sent"
- ✅ Tras 5 intentos fallidos el mensaje pasa a "failed" (sin bucles infinitos)
- ✅ Prueba de correo desde el panel (botón "enviar correo de prueba")
- ✅ Correo de prueba a dirección inválida es rechazado con mensaje
- ✅ cron.php por URL sin token o con token incorrecto → 403
- ✅ cron.php por URL con token secreto → 200 OK
- ✅ Recordatorio de 24 h se envía por el cron cuando corresponde
- ✅ El recordatorio de 2 h queda programado en el futuro
- ✅ Al cancelar, los recordatorios pendientes se omiten ("skipped") y se envía cancelación
- ✅ Servicio con aprobación manual: reserva queda pendiente
- ✅ El cron cancela las citas pendientes vencidas
- ✅ Modo de respaldo: sin cron real, una visita dispara las tareas (cron_last_run se actualiza)

### WhatsApp (centro manual y API opcional) y captcha (10/10)

- ✅ Centro "Mensajes por enviar hoy" lista mensajes de WhatsApp con enlace wa.me
- ✅ El enlace wa.me trae el mensaje ya redactado y codificado
- ✅ Marcar como enviado (AJAX con CSRF) funciona y sale de la lista
- ✅ IDOR: un profesional no puede marcar mensajes de otro profesional
- ✅ API de WhatsApp Cloud: envía POST /vXX.X/{id}/messages con Bearer y plantilla aprobada
- ✅ API de WhatsApp: el mensaje queda "sent"
- ✅ API de WhatsApp: error de autenticación se registra y reintenta (sin perder el mensaje)
- ✅ Captcha (Turnstile/hCaptcha opcional): sin token y con token inválido se rechaza; válido pasa (verificación contra servidor simulado)
- ✅ Con captcha activo, la CSP permite solo el dominio del proveedor
- ✅ Sin captcha configurado, la CSP no incluye dominios externos

### ICS, exportaciones CSV, protección de datos, respaldo y migraciones (32/32)

- ✅ .ics válido: estructura VCALENDAR/VEVENT, CRLF, TZID America/Guatemala, líneas ≤75 bytes
- ✅ .ics: UID, DTSTAMP UTC, SUMMARY y LOCATION presentes; caracteres especiales escapados
- ✅ .ics: la hora de inicio coincide con la cita
- ✅ Feed ICS privado por profesional (token secreto): válido y con las citas
- ✅ Feed ICS con token inválido → 404
- ✅ Exportación CSV de clientes: codificación UTF-8 con BOM, encabezados y descarga
- ✅ CSV: neutraliza inyección de fórmulas (=, @, +, -)
- ✅ CSV de citas: encabezado correcto y filas con la estructura esperada
- ✅ Reportes: páginas de reportes y CSV por servicio/profesional responden
- ✅ Importación CSV: crea válidos, omite duplicados e inválidos
- ✅ Importación: archivo que no es CSV es rechazado
- ✅ Exportación de datos de un cliente (JSON): ficha, citas y respuestas
- ✅ Eliminar datos de un cliente: borra ficha, citas, mensajes y archivos del disco
- ✅ Registro de auditoría guarda acciones sensibles (login, exportación, eliminación)
- ✅ Cliente bloqueado no puede reservar en línea
- ✅ El personal sí puede agendarle manualmente
- ✅ Marcar "no asistió" incrementa el contador del cliente
- ✅ Corregir el estado revierte el contador; la cita vuelve a bloquear el horario
- ✅ Límite configurable de citas próximas por cliente en línea (5)
- ✅ Servicio a domicilio exige dirección
- ✅ Servicio a domicilio con dirección: se guarda en la cita
- ✅ Validación de teléfono Guatemala: 8 dígitos, +502, espacios y guiones
- ✅ Formato de quetzales Q1,250.00 y fechas dd/mm/aaaa
- ✅ Slug sin tildes ni símbolos; token de 128 bits (32 hex)
- ✅ Respaldo .sql descargable (un clic) con todas las tablas
- ✅ El respaldo se restaura sin errores en una BD nueva y los conteos coinciden
- ✅ Modelo de datos: existen las 27 tablas requeridas
- ✅ Llaves foráneas e índices de disponibilidad presentes
- ✅ Migraciones numeradas: una migración nueva se aplica automáticamente una sola vez
- ✅ La migración no se repite
- ✅ Todas las pantallas públicas y del panel responden 200
- ✅ El registro de errores no contiene avisos PHP ni excepciones durante toda la suite

## 3. Navegador headless (Chromium) — pantallas públicas y del panel

81 combinaciones página×ancho (360, 768 y 1440 px): 7 páginas públicas/login y 20 pantallas del panel con sesión de administrador.

| Comprobación | Resultado |
|---|---|
| Errores o avisos de consola | **0** |
| Peticiones a recursos externos (CDN, fuentes, analítica) | **0** (todo local) |
| Imágenes rotas | **0** |
| Desbordes horizontales | 4 detectados en el panel a 360 px (nueva cita, ficha de profesional, mensajes, compartir) → **corregidos** y reverificados: 0 |
| Contraste WCAG AA (texto sobre fondos sólidos, 4.5:1 / 3:1 en texto grande) | 1 fallo (títulos de grupo del menú lateral 4.28:1) → **corregido**; resto sin hallazgos |
| Foco visible con teclado (Tab) | Anillo dorado de 2 px en enlaces/botones; en campos, anillo por sombra |
| Peso de la página pública (HTML+CSS+JS+fuentes, sin comprimir) | Inicio 144–158 KB; Reserva 173 KB (**< 1 MB**; objetivo cumplido con margen) |
| Flujo de reserva completo en móvil 390 px (servicio → fecha/hora → datos → confirmación) con clics reales | OK, sin errores de consola |
| Modo oscuro del panel | Revisado visualmente |
| Instalación en subcarpeta (`/clinica/`) | Páginas, assets, login y panel OK con rutas base correctas |

## 4. Higiene del paquete (aurea-agenda.zip)
- Sin archivos de prueba, herramientas de desarrollo, logs, `config.php`, `installed.lock`, sesiones, respaldos ni cargas.
- Sin credenciales, claves ni rutas absolutas del entorno de desarrollo (búsqueda exhaustiva sin hallazgos).
- Sin datos de demostración (solo si el cliente los pide en el instalador). Sin texto de relleno.
- Contenido en la raíz del ZIP (sin carpeta contenedora). Tamaño ≈ 400 KB.

## 5. Limitaciones reales (lo que NO se pudo probar o no está incluido)

- **Fotografías reales**: el entorno no tiene acceso a Unsplash/Pexels (el proxy respondió 403). La portada usa una composición vectorial propia (SVG/CSS) con la inicial del negocio; el administrador sube su fotografía, logo y fotos de profesionales desde el panel. `CREDITOS.txt` lo documenta. No se incluyen imágenes WebP de stock.
- **PHP 8.0**: la suite con base de datos se ejecutó en PHP 8.3. En PHP 8.0.30 real (WASM, sin extensión MySQL) se verificó sintaxis, compilación de todas las clases y lógica pura, pero no el flujo completo con MySQL.
- **Base de datos**: probado con MariaDB 10.11. No se probó MySQL 5.7 ni MariaDB 10.3 (el SQL usa solo características de esas versiones).
- **Servidor web**: se usó el servidor embebido de PHP emulando `.htaccess`. No hay Apache/LiteSpeed/Nginx en el entorno; las reglas de `.htaccess` y `docs/nginx.conf.example` no se ejecutaron. La protección de carpetas internas se verificó con el router de pruebas equivalente.
- **SMTP**: probado AUTH LOGIN sin cifrado contra un servidor local. Los modos STARTTLS e SSL implícito están implementados pero no se probaron contra un servidor real.
- **Integraciones externas**: WhatsApp Business Cloud API y Cloudflare Turnstile/hCaptcha se probaron contra simuladores locales (formato de petición, cabeceras, manejo de errores y reintentos), **no contra los servicios reales de Meta/Cloudflare/hCaptcha**. WhatsApp API requiere cuenta Meta del cliente y plantillas aprobadas.
- **Cron real**: se ejecutó `cron.php` por CLI y por URL con token, y el modo por visitas; no se instaló una tarea en un crontab real.
- **Accesibilidad**: verificación automática de contraste y foco más revisión visual; sin pruebas con lectores de pantalla ni auditoría axe. Los botones con degradado dorado (texto #1A1409 sobre #8C6A2B–#E9D29A) se calcularon aparte (> 4.5:1) pero no los midió la herramienta automática.
- **Alcance funcional**: la agenda permite crear, mover (formulario), cancelar y bloquear horarios, pero **no hay arrastrar y soltar**. Los paquetes de sesiones se venden y consumen desde el panel (no en la reserva pública). La facturación FEL y los cobros con tarjeta en línea están fuera de alcance (solo enlace de pago externo configurable, sin integraciones por API).
- **Zonas horarias distintas de America/Guatemala** y horarios de verano no se probaron (el cálculo usa la zona configurada de PHP).
- **Carga**: concurrencia verificada con 50 reservas simultáneas por horario; no se hicieron pruebas de carga sostenida.

## 6. Cobertura frente al plan de verificación
| Requisito (sección 11) | Estado |
|---|---|
| 1. php -l y PHP 8.0 | Cumplido (8.3 + 8.0.30 real) |
| 2. MariaDB y servidor PHP locales | Cumplido |
| 3. Instalación desde cero y bloqueo | Cumplido |
| 4. Suite automatizada (reserva, token, feriados, buffers, aviso/anticipación, grupos, 50 concurrentes, cupones, paquetes, espera, roles/IDOR, CSRF, SQLi/XSS, archivos maliciosos, límite de login, cola SMTP, cron CLI/URL, .ics, CSV) | Cumplido (229 pruebas) |
| 5. Navegador headless 360/768/1440 | Cumplido (ver límites de accesibilidad) |
| 6. Revisión de higiene | Cumplido |
| 7. Corregir y repetir | Cumplido (ver sección 9) |

## 7. Cómo repetir las pruebas
```
tools/build.sh /tmp/aurea.zip && mkdir /tmp/run && unzip -q /tmp/aurea.zip -d /tmp/run
php tests/run.php /tmp/run      # requiere MariaDB (usuario aurea) y python3
```
(El código de pruebas vive en el repositorio, fuera del ZIP de producción.)

## 8. Decisiones de seguridad relevantes
- CSRF obligatorio en TODo POST (panel: ligado a sesión; público: token firmado HMAC con vencimiento, compatible con el widget en iframes de terceros).
- CSP con `script-src 'self'` (sin inline), `style-src` con nonce; `style-src-attr 'unsafe-inline'` solo para posiciones calculadas de la agenda.
- Secretos (SMTP, tokens de WhatsApp, captcha) cifrados en BD con AES-256-GCM y la clave de `config.php`.
- Archivos privados fuera del acceso web, nombre aleatorio, entrega por script autorizado con `sandbox` CSP.

## 9. Defectos hallados por la propia verificación y corregidos
1. Instalador: `base_path()` dejaba `/instalar` en la URL base guardada (error de desplazamiento) → enlaces de correos/WhatsApp incorrectos.
2. Cron: candado de ejecución leído de la caché de archivo → bloqueo permanente tras la primera ejecución; además `rowCount` devolvía 0 al repetir en el mismo segundo.
3. Concurrencia: interbloqueos InnoDB por bloqueos de rango entre agendas contiguas del índice → lectura de solapamientos sin `FOR UPDATE` bajo bloqueo de la fila del profesional + reintento ante SQLSTATE 40001.
4. Caché de configuración con `include` quedaba obsoleta por OPcache hasta 2 s → ahora JSON.
5. Vista de servicios: contador numerado afectado por variable compartida; botón dorado ilegible en sección oscura; avatares con inicial del título ("D" de Dr.).
6. Resumen de reserva mostraba `[object HTMLElement]` (aplanado de nodos); aceptar oferta de lista de espera fallaba por campos obligatorios del formulario; botón Imprimir usaba un manejador en línea bloqueado por CSP.
7. Desbordes horizontales a 360 px y un contraste insuficiente en el panel.
