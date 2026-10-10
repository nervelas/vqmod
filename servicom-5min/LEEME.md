# Tu web en 5 minutos — Servicom

Portal que crea webs WordPress reales (informativa o tienda) en `slug.servicom.gt`, con vista previa, pago por transferencia y publicación.
Requisitos del hosting: PHP 8.0+ (zip, dom, xml, mbstring, fileinfo, curl, gd o imagick, pdo_mysql, openssl; **no** requiere sodium: los secretos se cifran con AES-256-GCM de OpenSSL), MySQL/MariaDB, Apache con mod_rewrite, cPanel con AutoSSL.

## 1. Subir e instalar (5 pasos)
1. **Subir el ZIP** `servicom-5min-portal.zip` a la carpeta del subdominio del portal (p. ej. el docroot de `crear.servicom.gt`) y descomprimir. **Nunca** en el docroot de `servicom.gt`.
2. En cPanel cree una base de datos y un usuario (todos los privilegios) y un **token de API** (cPanel → Seguridad → *Administrar tokens de API*; copie el valor). Abra `https://crear.servicom.gt/install.php`: verifica requisitos y pide la BD, el dominio base (`servicom.gt`), el subdominio del portal (`crear`), la carpeta de webs (por defecto `/home/USUARIO/webs-clientes`, **fuera** de `public_html`), los datos de cPanel (servidor `localhost`, usuario, token y carpeta personal `/home/USUARIO`) y su usuario. Comprueba el token al instalar. El instalador se elimina solo y guarda la configuración en `../servicom-secrets/config.php` (fuera del docroot) si puede.
3. **Hosting único** (no hay selector ni pantalla de hostings; la estructura interna queda lista para un segundo hosting en el futuro, desactivado): el portal y TODAS las webs de clientes viven en este mismo hosting (el de servicom.gt). Si cambia el token, edítelo en el panel → *Ajustes → cPanel*.
4. **Paquete base** (una sola vez; repetir para actualizar): por SSH o por *Cron Jobs* ejecute
   `php /home/USUARIO/.../tools/build_base.php` — descarga WordPress y WooCommerce **solo de wordpress.org** (la última versión compatible con PHP 8.0; si alguna exige más, lo avisa) y copia el tema/mu-plugin de Servicom a `webs-clientes/_base`.
5. **Cron diario** (borra vistas previas vencidas a los 15 días con todos sus archivos, avisa renovaciones, reanuda construcciones):
   `0 3 * * * /usr/local/bin/php /home/USUARIO/RUTA_DEL_PORTAL/tools/cron.php >/dev/null 2>&1`
   (si no lo configura, un «cron perezoso» se dispara con el tráfico del portal como respaldo).

Luego, en el panel → **Ajustes**: datos bancarios, correo y WhatsApp del dueño, clave de API de Claude, topes de gasto, SMTP (opcional). Revise **Diagnóstico** (token válido, espacio, extensiones, último cron, prueba de IA).

## Cómo son las webs que genera (motor LUXE)
- Diseño propio de lujo, sin Elementor: portada cinematográfica, franja de servicios, «Quiénes somos», tarjetas de servicios con iconos premium, «Por qué elegirnos», frase destacada, proceso, galería, preguntas frecuentes, llamado a la acción, contacto con formulario y mapa, botones flotantes de **llamar, WhatsApp y correo**, animaciones suaves y diseño compacto y adaptable a celular.
- **Los colores salen del logo** del cliente (con contraste verificado); sin logo se usa una paleta de lujo del rubro. El «ambiente» del formulario (oscuro/claro y tipo de letra) solo cambia la atmósfera.
- Con **muy poca información** (logo + nombre de servicios) el sistema redacta los textos con IA (sin inventar años, cifras, premios ni testimonios), completa servicios típicos del rubro si hay menos de 4 (marcados como sugeridos en el pedido) y rellena las imágenes: fotos del cliente/presentación primero; luego fotos de stock relevantes (Pixabay con su clave gratuita e inmediata de **Ajustes → Imágenes** (pixabay.com/api/docs; también admite Pexels), u Openverse sin clave); y si no hay, **arte generado** en los colores de la marca (nunca recuadros vacíos).
- **Editor en vivo para el cliente**: al entrar a su web con su usuario ve la barra «Editar mi web»; hace clic en cualquier texto, imagen, ícono o botón y lo cambia; también datos del negocio, redes, logo, colores, tipografías, mostrar/ocultar/reordenar secciones, agregar o quitar servicios, preguntas, galería; con «Deshacer». El menú **INSTRUCCIONES** de su panel explica cada tarea con botones que llevan al elemento exacto.
- Para actualizar el diseño en un portal ya instalado: suba el parche, y ejecute una vez `php tools/build_base.php --only-pack` (actualiza tema y mu-plugin del paquete base sin descargar nada). Las webs nuevas usan el diseño nuevo; las anteriores se regeneran con «Regenerar».

- **Modo «solo un archivo»:** si el cliente sube únicamente su presentación (PDF recomendado; también .pptx/.docx), no se le pide nada más. La IA extrae nombre, servicios, textos y contactos; el sistema extrae del PDF el **logo** y las **fotos** (lector propio en PHP, sin Imagick), toma los **colores de la marca** del logo y de lo que la IA detecta, y crea diseño, efectos, textos faltantes e imágenes de apoyo. Lo que no venga (WhatsApp, correo, dirección…) **no se inventa**: queda como *pendiente*.
- **Pendientes en el panel de la web:** menú **PENDIENTES** (con contador), aviso en el Escritorio, widget y recuadro en INSTRUCCIONES. Cada dato que falta tiene un botón «Completar ahora» que abre el editor con ese campo ya enfocado (`/?sc_edit=1&sc_biz=whatsapp`). También puede marcar «Este negocio no lo tiene».
- **Guía para el cliente:** `/guia-presentacion` en el portal explica qué debe llevar el PDF e incluye una plantilla descargable; se enlaza desde el asistente y el pie de página.

## 2. Subdominios y SSL
- Cada web es un subdominio con carpeta propia (`webs-clientes/<slug>`); se crean/borran por API de cPanel. Los subdominios reservados (`www, mail, webmail, cpanel, whm, ftp, smtp, imap, pop, ns1, ns2, autodiscover, autoconfig, admin, panel, portal, api, crear, cpw, ctv, demo, test, dev, staging, blog, tienda, soporte` y el del portal) nunca se asignan.
- El sistema **no toca** archivos, DNS ni correos de `servicom.gt`.
- **AutoSSL** emite el certificado de cada subdominio en unos minutos; el portal muestra «Preparando tu vista previa» y espera (por defecto hasta 15 min) antes de mostrar el enlace. Si su hosting ofrece **certificado comodín** (`*.servicom.gt`) instálelo en cPanel → SSL/TLS: las vistas previas quedan con HTTPS al instante; no afecta a `servicom.gt`.
- El DNS debe resolver `*.servicom.gt` al hosting (registro A comodín o un A por subdominio; cPanel suele crearlo al crear el subdominio).

## 3. Flujo diario
1. El cliente llena el formulario (o sube su presentación) → vista previa real en `slug.servicom.gt` (privada, noindex).
2. El cliente sube el comprobante → en el panel verá **Pago por revisar** → **Aprobar** (publica, crea el usuario del cliente y le envía el enlace para definir su contraseña) o **Rechazar** con motivo.
3. Usted recibe por correo la lista de **correos y dominio** a crear manualmente (el cliente espera 2 días hábiles). Más tarde: botón **Asignar dominio** cuando el dominio ya apunte al hosting.
4. Renovación = publicación + 365 días; aviso 30 días antes; botones *Marcar renovado* y *Suspender*.

## 4. Costos de IA
Tope diario y total en Ajustes (por defecto USD 1/día y USD 10 total); al alcanzarlo se usan textos base automáticamente. Cargue crédito inicial en console.anthropic.com. Hay máx. 3 análisis de presentación y 3 regeneraciones por pedido, y límites por IP.

## 5. No puedo entrar al panel
1. El acceso tolera espacios sobrantes (frecuentes en teclados de teléfono) y el correo no distingue mayúsculas. Use «Mostrar contraseña» para verificar lo que escribe. Tras 6 intentos fallidos el acceso se bloquea 15 minutos.
2. Si aun así no entra: abra `https://crear.servicom.gt/admin/recuperar`. La pantalla muestra un nombre de archivo (`recuperar-XXXXXXXXXXXX.txt`). Créelo (vacío) con el Administrador de archivos de cPanel en la carpeta del portal, vuelva a la pantalla, defina correo y contraseña nuevos. Esto demuestra que usted controla el hosting; el archivo se borra solo y la verificación en dos pasos se desactiva.

## Decisiones y limitaciones
Ver `INFORME.md` y `docs/DECISIONES.md` (si no vienen en el ZIP, están en el repositorio). Fotos de stock: ver `library/FALTANTES.md`.
