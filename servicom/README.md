# Servicom — Sitio web + Panel de administración

Sitio web corporativo de alta gama para **Servicom** (diseño de páginas web en Guatemala),
construido en **PHP 8.0+ / MySQL**, listo para hosting compartido con cPanel.
Sin Node.js, sin Composer, sin dependencias externas en producción.

---

## Instalación rápida (5 minutos)

1. **Suba y descomprima** el contenido del ZIP dentro de `public_html/` (la raíz de su dominio).
   Al terminar, en `public_html/` deben quedar `index.php`, `admin/`, `assets/`, etc.
   *(No debe quedar una carpeta intermedia.)*

2. **Cree la base de datos** en cPanel → *Bases de datos MySQL*:
   una base, un usuario y asigne el usuario a la base **con todos los privilegios**.
   Anote el nombre completo (normalmente `cpanelusuario_nombre`).

3. **Abra en el navegador**: `https://sudominio.com/install/`

4. **Complete el formulario** (datos de la base, dirección del sitio y su usuario administrador)
   y pulse **Instalar ahora**. El instalador crea las tablas, carga el contenido inicial
   y genera `config/config.php`.

5. **Borre la carpeta `install/`** del servidor. Es el único paso obligatorio de seguridad.

6. Entre al panel en `https://sudominio.com/admin/` con el usuario que acaba de crear.

### Instalación manual (alternativa)

1. Importe `database/schema.sql` y luego `database/seed.sql` desde phpMyAdmin.
2. Copie `config/config.sample.php` a `config/config.php` y complete los datos.
3. Entre a `/admin/` con **admin / Servicom2026\*** y **cambie la contraseña de inmediato**.

---

## Requisitos

- PHP **8.0 o superior** (recomendado 8.2+) con `pdo_mysql`, `mbstring`, `fileinfo` y `gd`.
- MySQL 5.7+ / MariaDB 10.3+.
- Apache con `mod_rewrite` activo (para las URLs limpias).
- Permisos de escritura en `config/`, `uploads/media/` y `storage/` (755).

---

## Qué puede editar desde el panel

| Sección | Qué controla |
|---|---|
| **Datos del sitio** | Nombre, logotipos, favicon, teléfonos, WhatsApp, correo, dirección, horario, mapa, redes sociales, efectos visuales, textos del formulario y modo mantenimiento |
| **Temas visuales** | Los 8 temas (4 oscuros y 4 claros) con vista previa, activación y edición de la paleta |
| **Slider principal** | Imágenes, textos, botones, iconos, alineación y orden de cada diapositiva |
| **Secciones y textos** | Encabezado, subtítulo, texto y botones de cada sección; activar u ocultar secciones completas |
| **Servicios** | Nombre, URL, icono, descripción, características, imagen, precio y SEO propio |
| **Planes · Portafolio · Proceso · Indicadores · Testimonios · Preguntas frecuentes** | Alta, edición, orden, visibilidad y eliminación |
| **Actualidad Web** | Publicaciones del blog con imagen, fecha y SEO |
| **Páginas** | Contenido y SEO de cada página, prioridad en el sitemap e indexación |
| **Menú de navegación** | Texto, enlace, **icono**, ubicación (cabecera o pie), botón destacado y orden |
| **Biblioteca de imágenes** | Subida múltiple, textos alternativos y eliminación |
| **SEO y buscadores** | Metadatos globales, datos del negocio (Schema.org), Analytics, Search Console y diagnóstico |
| **Mensajes recibidos** | Solicitudes del formulario, con respuesta directa por correo o WhatsApp |
| **Usuarios** | Personas con acceso al panel |

---

## SEO incluido

- Título, meta descripción, palabras clave, Open Graph y Twitter Cards **por página y por servicio**.
- Datos estructurados **Schema.org**: `ProfessionalService`, `WebSite`, `BreadcrumbList`,
  `Service`, `BlogPosting` y `FAQPage`.
- `sitemap.xml` dinámico y `robots.txt` con la URL real del sitio.
- URLs amigables con barra final y canónicas automáticas.
- `hreflang="es-gt"`, `lang="es-GT"` y metadatos geográficos de Guatemala.
- Un único `H1` por página y jerarquía `H1→H2→H3` correcta.
- Imágenes con `alt`, `width`/`height` y carga diferida.
- Diagnóstico SEO en el panel que avisa de títulos largos o descripciones faltantes.

## Rendimiento

- **Cero librerías externas de JavaScript.** Todo el CSS y el JS son propios.
- Una sola hoja de estilos y un solo archivo JS, ambos cacheados un mes o más.
- Iconos como sprite SVG en línea: no generan peticiones y reducen los nodos del DOM.
- Imágenes vectoriales (SVG) para todo el arte del sitio: 92 KB para todo el set gráfico.
- Compresión GZIP/Brotli y caché de un año para estáticos, configurados en `.htaccess`.
- Fuentes de Google con `preconnect`, `preload` y `display=swap`; solo se carga el par
  tipográfico del tema activo.

## Seguridad

- PDO con **consultas preparadas** en todo el proyecto.
- Contraseñas con `password_hash` (bcrypt) y rehash automático.
- **Token CSRF** en todos los formularios del sitio y del panel.
- Escape de salida (`htmlspecialchars`) en todas las plantillas.
- Sesiones con `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS y carpeta propia.
- Bloqueo temporal tras 6 intentos fallidos de acceso.
- Subida de imágenes con validación MIME real, renombrado seguro y saneo de SVG.
- `uploads/` no ejecuta PHP; `includes/`, `config/`, `database/` y `storage/` están bloqueadas.
- Cabeceras `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y `Permissions-Policy`.
- Honeypot y control de tiempo mínimo antispam en el formulario de contacto.

---

## Estructura

```
index.php              Punto de entrada del sitio (enrutador)
sitemap.php            sitemap.xml dinámico
robots.php             robots.txt dinámico
config/                Configuración (un solo archivo)
database/              schema.sql y seed.sql
includes/              Clases y utilidades del núcleo
templates/             Plantillas del sitio público
  layout/              Cabecera, pie y encabezado de páginas internas
  sections/            Secciones reutilizables de la portada
admin/                 Panel de administración
assets/                CSS, JS e imágenes del diseño
uploads/media/         Imágenes que suba desde el panel
storage/               Sesiones, caché y registro de errores
install/               Instalador web (BÓRRELO tras instalar)
```

---

## Después de instalar: lista de tareas

1. Suba **su logotipo real** (versión clara y oscura) en *Datos del sitio → Identidad*.
2. Revise teléfonos, WhatsApp, correo, dirección y horario.
3. Complete sus redes sociales (Instagram, LinkedIn, TikTok…).
4. Elija el tema visual definitivo en *Temas visuales*.
5. Sustituya las tarjetas del **Portafolio** por proyectos reales de sus clientes.
6. Publique **testimonios reales**; los de ejemplo vienen desactivados a propósito.
7. Conecte Google Analytics y Search Console en *SEO y buscadores*.
8. Envíe `https://sudominio.com/sitemap.xml` a Google Search Console.
9. En `.htaccess`, descomente el bloque de **forzar HTTPS** cuando su certificado esté activo.
10. Cambie la contraseña del administrador si usó la instalación manual.

---

## Preguntas frecuentes de instalación

**Las páginas internas dan error 404.**
Falta `mod_rewrite` o `AllowOverride` está desactivado. Actívelos en cPanel o pida
a su proveedor que lo habilite.

**El sitio vive en una subcarpeta.**
En `.htaccess` descomente `RewriteBase /subcarpeta` y ponga la URL completa con la
subcarpeta en el instalador.

**No puedo subir imágenes.**
Asigne permisos 755 a `uploads/` y `uploads/media/`.

**No llegan los correos del formulario.**
Los mensajes **siempre** quedan guardados en el panel, en *Mensajes recibidos*.
El aviso por correo usa la función `mail()` de PHP; si su hosting la tiene desactivada,
consúltelo con su proveedor.

---

© Servicom · Diseño de páginas web en Guatemala
