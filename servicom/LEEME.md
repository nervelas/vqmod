# CotizaPro B2B — instalación y uso

Catálogo técnico, cotizador en línea y seguimiento de cotizaciones para **una
empresa**. PHP puro, sin composer, pensado para hosting cPanel.

## 1. Requisitos del hosting
PHP 8.0 o superior con **PDO MySQL, mbstring, GD, fileinfo, zip y openssl**
(WebP recomendado) · MySQL 8 / MariaDB 10.4+ · Apache con `mod_rewrite` ·
HTTPS activo (Let's Encrypt de cPanel).

## 2. Subir e instalar
1. En cPanel → **Administrador de archivos**, entre a `public_html` (o a la
   carpeta del subdominio) y suba `cotizapro.zip`. Clic derecho → **Extraer**.
2. En **MySQL Databases** cree una base, un usuario y asígnelo con *All
   Privileges*. Anote nombre, usuario y contraseña.
3. Abra `https://SUDOMINIO/install/` y siga los 3 pasos (requisitos → base de
   datos → empresa y administrador). Marque *Instalar datos de demostración*
   si quiere ver el sistema con catálogo y cotizaciones de ejemplo.
4. El instalador crea `config/config.php`, se bloquea solo (`install/.lock`) y
   ya puede **borrar la carpeta `/install`**.
5. Verifique que `/storage` y `/config` tengan permisos de escritura (755).

El sitio público queda en la raíz de la instalación y el panel en `/panel`.
Funciona igual en el dominio, en un subdominio o en una subcarpeta: no hay
rutas fijas en el código.

## 3. Tarea programada (cron)
En cPanel → **Trabajos cron**, cada 15 minutos. El comando exacto, con su
token, aparece en **Panel → Configuración**:

```
*/15 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=XXXX" >/dev/null 2>&1
```

Ejecuta los recordatorios de seguimiento, el informe mensual al administrador,
la limpieza de temporales y el respaldo automático de los domingos.

## 4. Personalizar la empresa
**Panel → Configuración**: nombre, razón social, NIT, logo (regenera los iconos
de la PWA), imagen del hero, colores (8 temas técnicos o personalizados), datos
fiscales, moneda, IVA, numeración y textos del PDF, condiciones por omisión,
SMTP, WhatsApp, visibilidad de precios, días de recordatorio y SEO.
Los usuarios y sus roles (administrador, vendedor, visor) se gestionan en
**Panel → Usuarios**.

## 5. Importar el catálogo desde WooCommerce
En WooCommerce: **Productos → Exportar** (deje todas las columnas). Luego en
CotizaPro: **Panel → Importar** → suba el CSV. Las columnas de WooCommerce
(`SKU`, `Name`, `Regular price`, `Categories`, `Attribute 1 name/value`…) se
reconocen solas; revise el mapeo, vea la vista previa y confirme. Al terminar
obtiene un reporte de filas importadas y de errores. También hay plantilla
propia en Excel y un archivo de ejemplo en `database/ejemplos/`.
Las categorías con formato `Padre > Hijo` se crean con su jerarquía.

## 6. Credenciales de demostración
| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | `admin@industrialperez.gt` | `Perez2026!` |
| Vendedor | `vendedor1` | `Venta2026!` |
| Visor (solo reportes) | `visor1` | `Visor2026!` |

Además existe la cuenta de administrador que usted creó en el instalador.
**Cambie estas contraseñas antes de salir a producción.**

## 7. Notas
- Respaldos: **Panel → Respaldos** (manual) y automático semanal en
  `/storage/backups/`. Esa carpeta no es accesible desde el navegador.
- Correo: configure su SMTP en **Panel → Configuración**; si lo deja vacío se
  usa la función `mail()` del hosting.
- Registros de error: `/storage/logs/`. Nunca se muestran al visitante.
- **Fotografía**: el sistema trae imágenes industriales y láminas técnicas
  generadas por el propio tema (`php tools/generate-images.php` y
  `php tools/generate-plates.php`). Para reemplazarlas por fotografía real de
  Unsplash desde su hosting: pegue las URL en `tools/fotos.txt` y ejecute
  `php tools/fetch-photos.php`, o con su clave de la API:
  `php tools/fetch-photos.php SU_CLAVE_UNSPLASH`. El script genera JPG + WebP
  en tres tamaños y la miniatura borrosa, y guarda los créditos de autoría.
