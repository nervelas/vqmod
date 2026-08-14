# Plataforma de Ligas de Fútbol

Plataforma web **premium, profesional, multiliga y 100% administrable** para gestionar
ligas de fútbol. Construida en **PHP 8 + MariaDB/MySQL** con PDO y consultas preparadas.
Sin Node.js, Docker, Supabase, Firebase ni Vercel como requisito de producción. Lista
para subir directamente a **cPanel**.

## Requisitos

- PHP **8.0+** (compatible con 8.0, 8.1, 8.2, 8.3, 8.4)
- MariaDB o MySQL
- Apache (cPanel) con `mod_rewrite`, `mod_headers` recomendados
- Extensiones PHP: `pdo_mysql`, `fileinfo`, `mbstring`, `gd`

## Instalación (cPanel)

1. Sube el contenido del proyecto a `public_html/` (o a un subdominio).
2. Crea una base de datos MySQL y un usuario con todos los privilegios (desde cPanel → MySQL Databases).
3. Visita `https://tu-dominio.com/install/` en el navegador.
4. Completa:
   - Datos de la base de datos (host, puerto, nombre, usuario, contraseña).
   - Datos del **Super Administrador** (nombre, usuario, correo, contraseña).
5. Pulsa **Instalar plataforma**. Se crean todas las tablas, roles, permisos,
   configuración y los **10 temas visuales**.
6. **Elimina la carpeta `/install`** del servidor por seguridad.
7. Entra al panel en `https://tu-dominio.com/admin/`.

> Tras instalar, la plataforma queda **limpia**: 0 ligas, 0 torneos, 0 equipos,
> 0 jugadores, 0 partidos y 0 resultados. Todo el contenido deportivo lo crea el
> administrador desde el panel.

## Estructura

```
/                 Sitio público (index.php, liga.php)
/admin            Panel de administración
/app              Núcleo (clases, esquema, seed, configuración)
/assets           CSS y JavaScript
/uploads          Imágenes subidas (ejecución de código bloqueada)
/install          Asistente de instalación (eliminar tras instalar)
/backups          Copias de seguridad SQL (no accesible por web)
```

## Seguridad

- PDO con **consultas preparadas** en todo el código (protección SQL Injection).
- Escape de salida en todas las vistas (protección XSS).
- **CSRF** en todos los formularios.
- Sesiones endurecidas: `HttpOnly`, `SameSite`, `Secure` (bajo HTTPS), regeneración periódica.
- **Rate limiting** de inicio de sesión.
- Cabeceras de seguridad (CSP, X-Frame-Options, nosniff, etc.).
- Subidas: solo JPG/PNG/WEBP, validación de MIME real, dimensiones y contenido;
  renombrado aleatorio; **ejecución de PHP bloqueada** en `/uploads`.
- Roles y permisos verificados en el backend. Nunca se puede eliminar al último Super Administrador.
- Auditoría de acciones (usuario, acción, módulo, antes/después).

## Temas visuales

10 temas premium visualmente diferenciados, cada uno con 4 colores base
(fondo, principal, secundario, acento). El resto de la paleta (textos, bordes,
hover, focus, superficies, sombras) se **deriva automáticamente garantizando
contraste y accesibilidad**. Los temas solo se cambian desde
**Configuración → Apariencia**; el público no puede modificarlos. Cada liga puede
usar un tema distinto.

## Licencia

Uso privado del cliente.
