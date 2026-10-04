# Agenda Premium

Sistema de agendamiento de citas y reuniones, 100 % en español (Guatemala), con estilo de relojería de lujo (negro, oro y marfil).
Todo se administra desde el panel: eventos, horarios, equipo, recordatorios, pagos, marca y textos legales.

**Requisitos:** PHP 8.0 o superior (`pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl`; recomendado `gd` y `curl`), MySQL 5.7+ o MariaDB 10.3+, Apache con `.htaccess`. No usa Composer, Node ni CDN.

## Instalación en 5 pasos
1. Crea una base de datos MySQL/MariaDB (utf8mb4) desde el panel de tu hosting y anota servidor, nombre, usuario y contraseña.
2. Descomprime `agenda-premium.zip` directamente en la carpeta raíz del sitio (`public_html`) **o** en una subcarpeta; no hay carpeta contenedora.
3. Abre `https://tu-dominio.com/instalar/` y sigue los 4 pasos: requisitos → base de datos → negocio y administrador → profesión.
4. Entra a `https://tu-dominio.com/admin` con el correo y la contraseña que elegiste y completa el asistente de inicio.
5. **Borra la carpeta `/instalar`** (el instalador ya queda bloqueado) y programa el cron (ver abajo).

## Cron (recordatorios y correos)
Programa cada 5 minutos una de estas opciones:
- Por línea de comandos: `*/5 * * * * php /ruta/a/tu/sitio/cron.php`
- Por URL (hostings sin CLI): `*/5 * * * * curl -s "https://tu-dominio.com/cron.php?token=TU_TOKEN" > /dev/null` (el token aparece al terminar la instalación y en el panel: Estado del sistema).
Si no puedes programar nada, el sistema ejecuta un respaldo ligero cuando alguien visita el sitio, pero los recordatorios exactos requieren el cron.

## Cómo insertar la reserva en otros sitios
Panel → **Insertar y compartir**: enlace directo, modo en línea (iframe), ventana emergente, botón flotante, código QR descargable y shortcode.
Para WordPress, sube `extras/wordpress/agenda-premium-embed.php` a `wp-content/plugins/` (o comprímelo en ZIP), actívalo y usa `[agenda_premium url="https://tu-dominio.com" evento="consulta" modo="inline"]`.

## Respaldo
Panel → **Respaldo e importación** → "Crear respaldo ahora" (descarga un `.sql` comprimido). También puedes exportar/importar toda la configuración en JSON para instalar rápido en varios clientes. Recomendado: respaldo semanal y copia fuera del servidor.

## Actualización
1. Haz un respaldo desde el panel.
2. Sube los archivos nuevos del ZIP **sin sobrescribir** `config/config.php` ni la carpeta `storage/`.
3. Entra al panel: las migraciones numeradas se aplican solas.

## Servidores que no son Apache
- **LiteSpeed:** lee el `.htaccess` tal cual.
- **Nginx:** envía todo lo que no sea archivo real a `index.php` y bloquea las carpetas internas:
```
location ~ ^/(app|config|database|storage|tests|extras)/ { deny all; }
location ~ /\.(?!well-known) { deny all; }
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.x-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```
Si el sitio vive en una subcarpeta, antepón esa ruta a cada bloque.

## Más información
- Privacidad y marco legal: `LEGAL.md` · Licencias: `LICENCIAS.txt` · Créditos: `CREDITOS.txt`
- API REST y webhooks: `/api-docs` en tu propio sitio.
