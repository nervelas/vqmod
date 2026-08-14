# Centro Educativo Cristiano Fuente de Vida — Sitio web + Panel de administración

Sitio web institucional completo, **responsive** y **administrable**, construido en **PHP 8.2+ / MySQL**, listo para **hosting compartido con cPanel**. No requiere Node.js, Docker ni servicios externos en producción.

Incluye:

- Sitio público (inicio, nosotros, niveles, admisiones, galería, contacto).
- Panel de administración profesional en `/admin/`.
- Instalador web en `/install/`.
- Gestor de contenido: **todo el texto, imágenes, botones, enlaces, menú, accesos/plataformas, WhatsApp, SEO y footer son editables** desde el panel.
- Biblioteca multimedia con subida segura (validación MIME, renombrado seguro, sin ejecución de scripts en `uploads/`).
- Galería con álbumes y fotografías, carga diferida (lazy loading) y lightbox.
- Formularios (contacto y admisión) con validación, CSRF, honeypot antispam y almacenamiento en base de datos.
- SEO: title/description por página, Open Graph, Twitter Cards, `schema.org`, `sitemap.xml` dinámico y `robots.txt`.
- Respaldo de base de datos exportable desde el panel.
- Seguridad: PDO con *prepared statements*, `password_hash`, protección CSRF, sesiones seguras, escape de salida, protección de archivos sensibles.

---

## Requisitos

- PHP **8.1 o superior** (recomendado 8.2+), con extensiones: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`.
- MySQL / MariaDB.
- Apache con `mod_rewrite` (para URLs limpias).

## Instalación en cPanel (recomendada)

1. **Sube los archivos** al servidor. Normalmente descomprime el contenido del ZIP dentro de `public_html/` (o del subdominio correspondiente).
2. En cPanel crea una **base de datos MySQL**, un **usuario** y **asígnalo** a la base de datos (con todos los privilegios).
3. Visita en tu navegador: `https://tudominio.com/install/`
4. Completa el formulario:
   - Datos de la base de datos (servidor, nombre, usuario, contraseña).
   - Datos del administrador (nombre, correo, usuario, contraseña).
   - URL del sitio.
5. Presiona **Instalar ahora**. El instalador:
   - comprueba la conexión,
   - crea e importa las tablas,
   - inserta el contenido inicial,
   - crea el administrador,
   - genera `config/config.php`,
   - bloquea el instalador.
6. **Elimina la carpeta `/install/`** del servidor (por seguridad).
7. Ingresa al panel: `https://tudominio.com/admin/`

> Si tu hosting no tiene SSL aún, edita `.htaccess` y deja comentado el bloque *Force HTTPS* (ya viene comentado por defecto). Actívalo cuando tengas certificado.

## Estructura del proyecto

```
/                     Front controller (index.php), .htaccess, robots.txt, sitemap.php
/admin/               Panel de administración (login, dashboard, módulos)
/assets/              CSS, JS e imágenes del sitio
/config/              Configuración (config.php se genera en la instalación) — protegido
/database/            schema.sql y seed.sql (usados por el instalador)
/includes/            Núcleo PHP (DB, Auth, Settings, Media, Content, helpers) — protegido
/install/             Instalador web (eliminar tras instalar)
/templates/           Plantillas del sitio público
/uploads/media/       Imágenes subidas (sin ejecución de PHP)
```

## Administración: qué puedes editar

- **Páginas y secciones:** título, subtítulo, texto, imagen, fondo, ícono, botón (texto/URL/destino), orden, activar/desactivar, y SEO por página.
- **Menú:** etiquetas, enlaces internos o externos, destino y orden.
- **Accesos y plataformas:** Portal Académico, Pagos, Radio, etc. — nombre, ícono/imagen, URL real, destino y estado.
- **Galería:** álbumes y fotografías (subida múltiple, portada, orden, descripción).
- **Biblioteca multimedia:** subir/eliminar imágenes; selector reutilizable en todos los campos de imagen.
- **WhatsApp:** número, mensaje, texto del botón, activar/desactivar.
- **Configuración general:** nombre, logo, favicon, colores, teléfono, correo, dirección, redes sociales, footer, copyright, modo mantenimiento.
- **SEO:** títulos/descripciones por defecto, Open Graph, código de analítica.
- **Solicitudes:** ver y gestionar los envíos de contacto y admisión.
- **Administradores:** crear/editar/eliminar cuentas (contraseñas cifradas).
- **Respaldos:** exportar la base de datos.

## Plataformas externas

Los servicios especializados **mantienen su infraestructura real** y pueden reconfigurarse desde el panel sin tocar código:

- Portal académico / pagos / estados de cuenta → sistema en línea del colegio.
- Fuente de Vida Radio → plataforma de streaming oficial.

> Estos enlaces vienen pre-cargados con destinos conocidos. **Verifica y ajusta cada URL, número de WhatsApp y datos de contacto en el panel** antes de publicar, para que coincidan exactamente con los oficiales de la institución.

## Notas sobre imágenes

El proyecto incluye ilustraciones vectoriales (SVG) propias para logo, banners y niveles, de modo que **no hay imágenes rotas ni placeholders temporales**. Sustitúyelas por las fotografías oficiales del colegio desde **Biblioteca multimedia** y los campos de imagen de cada sección.

## Seguridad

- Contraseñas con `password_hash()` / `password_verify()`.
- Todas las consultas usan PDO con *prepared statements*.
- Protección CSRF en todos los formularios.
- Sesiones con `session_regenerate_id`, cookies `HttpOnly`/`SameSite` y `Secure` bajo HTTPS.
- Subidas validadas por MIME real; `uploads/` no ejecuta PHP.
- Carpetas `config/`, `includes/`, `database/` bloqueadas por `.htaccess`.

---

© Centro Educativo Cristiano Fuente de Vida.
