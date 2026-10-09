# Tu web en 5 minutos — Servicom

Portal que crea webs WordPress reales (informativa o tienda) en `slug.servicom.gt`, con vista previa, pago por transferencia y publicación.
Requisitos del hosting: PHP 8.0+ (zip, dom, xml, mbstring, fileinfo, curl, gd o imagick, pdo_mysql, sodium), MySQL/MariaDB, Apache con mod_rewrite, cPanel con AutoSSL.

## 1. Subir e instalar (5 pasos)
1. **Subir el ZIP** `servicom-5min-portal.zip` a la carpeta del subdominio del portal (p. ej. el docroot de `crear.servicom.gt`) y descomprimir. **Nunca** en el docroot de `servicom.gt`.
2. En cPanel cree una base de datos y un usuario (todos los privilegios). Abra `https://crear.servicom.gt/install.php`: verifica requisitos, pide BD, dominio base (`servicom.gt`), subdominio del portal (`crear`), carpeta de webs (por defecto `/home/USUARIO/webs-clientes`, **fuera** de `public_html`) y crea su usuario. El instalador se elimina solo y guarda la configuración en `../servicom-secrets/config.php` (fuera del docroot) si puede.
3. **Token de cPanel**: cPanel → Seguridad → *Administrar tokens de API* → crear token (conserve el valor). En el panel (`/admin`) → **Hostings → Agregar**: tipo *cPanel*, servidor `localhost`, su usuario, el token, la carpeta personal (`/home/USUARIO`) y la ruta de webs.
4. **Paquete base** (una sola vez; repetir para actualizar): por SSH o por *Cron Jobs* ejecute
   `php /home/USUARIO/.../tools/build_base.php` — descarga WordPress, Elementor, WooCommerce y Fluent Forms **solo de wordpress.org** (la última versión compatible con PHP 8.0; si alguna exige más, lo avisa) y copia el tema/mu-plugin de Servicom a `webs-clientes/_base`.
5. **Cron diario** (borra vistas previas vencidas a los 15 días con todos sus archivos, avisa renovaciones, reanuda construcciones):
   `0 3 * * * /usr/local/bin/php /home/USUARIO/RUTA_DEL_PORTAL/tools/cron.php >/dev/null 2>&1`
   (si no lo configura, un «cron perezoso» se dispara con el tráfico del portal como respaldo).

Luego, en el panel → **Ajustes**: datos bancarios, correo y WhatsApp del dueño, clave de API de Claude, topes de gasto, SMTP (opcional). Revise **Diagnóstico** (token válido, espacio, extensiones, último cron, prueba de IA).

## 2. Subdominios y SSL
- Cada web es un subdominio con carpeta propia (`webs-clientes/<slug>`); se crean/borran por API de cPanel. Los subdominios reservados (`www, mail, webmail, cpanel, whm, ftp, smtp, imap, pop, ns1, ns2, autodiscover, autoconfig, admin, panel, portal, api, crear, cpw, ctv, demo, test, dev, staging, blog, tienda, soporte` y el del portal) nunca se asignan.
- El sistema **no toca** archivos, DNS ni correos de `servicom.gt`.
- **AutoSSL** emite el certificado de cada subdominio en unos minutos; el portal muestra «Preparando tu vista previa» y espera (por defecto hasta 15 min) antes de mostrar el enlace. Si su hosting ofrece **certificado comodín** (`*.servicom.gt`) instálelo en cPanel → SSL/TLS: las vistas previas quedan con HTTPS al instante; no afecta a `servicom.gt`.
- El DNS debe resolver `*.servicom.gt` al hosting (registro A comodín o un A por subdominio; cPanel suele crearlo al crear el subdominio).

## 3. Segundo hosting (agente)
1. Suba `servicom-5min-agente.zip` a una carpeta con URL propia del segundo hosting (p. ej. `https://hosting2.../agente/`).
2. Abra `instalar-agente.php`: pida usuario/token de cPanel de **ese** hosting, ruta de webs y URL del portal. Muestra la URL del agente y un **secreto** (cópielo).
3. Ejecute `php tools/build_base_agent.php` (construye su propio `_base`).
4. En el portal → Hostings → Agregar tipo *Agente* con la URL y el secreto. Al crear/demos puede elegir en qué hosting se crea cada web.
Seguridad: mensajes firmados con HMAC + marca de tiempo + nonce de un solo uso; lista de IP opcional; los archivos del cliente se descargan con URLs firmadas de un solo uso.

## 4. Flujo diario
1. El cliente llena el formulario (o sube su presentación) → vista previa real en `slug.servicom.gt` (privada, noindex).
2. El cliente sube el comprobante → en el panel verá **Pago por revisar** → **Aprobar** (publica, crea el usuario del cliente y le envía el enlace para definir su contraseña) o **Rechazar** con motivo.
3. Usted recibe por correo la lista de **correos y dominio** a crear manualmente (el cliente espera 2 días hábiles). Más tarde: botón **Asignar dominio** cuando el dominio ya apunte al hosting.
4. Renovación = publicación + 365 días; aviso 30 días antes; botones *Marcar renovado* y *Suspender*.

## 5. Costos de IA
Tope diario y total en Ajustes (por defecto USD 1/día y USD 10 total); al alcanzarlo se usan textos base automáticamente. Cargue crédito inicial en console.anthropic.com. Hay máx. 3 análisis de presentación y 3 regeneraciones por pedido, y límites por IP.

## Decisiones y limitaciones
Ver `INFORME.md` y `docs/DECISIONES.md` (si no vienen en el ZIP, están en el repositorio). Fotos de stock: ver `library/FALTANTES.md`.
