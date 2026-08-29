# CotizaPro B2B — instalación y uso

Catálogo técnico, cotizador en línea y seguimiento de cotizaciones para varias
empresas en una sola instalación. PHP puro, sin composer, para hosting cPanel.

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
   datos → superadministrador). Marque *Instalar datos de demostración* si
   quiere ver el sistema con catálogo y cotizaciones de ejemplo.
4. El instalador crea `config/config.php`, se bloquea solo (`install/.lock`) y
   ya puede **borrar la carpeta `/install`**.
5. Verifique que `/storage` y `/config` tengan permisos de escritura (755).

Funciona igual en la raíz del dominio o en una subcarpeta: no hay rutas fijas.

## 3. Tarea programada (cron)
En cPanel → **Trabajos cron**, cada 15 minutos. El comando exacto, con su
token, aparece en **Superadmin → Ajustes**:

```
*/15 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=XXXX" >/dev/null 2>&1
```

Ejecuta los recordatorios de seguimiento, el informe mensual al administrador,
la limpieza de temporales y el respaldo automático de los domingos.

## 4. Crear una empresa nueva
**Superadmin → Empresas → Nueva empresa**: nombre, identificador de URL, plan,
estado, vencimiento y el correo/contraseña de su administrador. Queda publicada
en `https://SUDOMINIO/e/identificador`.

**Con dominio o subdominio propio:** en cPanel cree el subdominio (o apunte el
dominio) a **la misma carpeta** de esta instalación y escriba ese dominio en el
campo *Dominio propio* de la empresa. El sistema lo detecta y sirve su catálogo
en la raíz de ese dominio.

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
| Superadministrador | `admin@plataforma.gt` | `Admin2026!` |
| Administrador de empresa | `admin@industrialperez.gt` | `Perez2026!` |
| Vendedor | `vendedor1` | `Venta2026!` |

Segunda empresa de prueba (verifica el aislamiento de datos):
`admin@uniformesroca.gt` / `Roca2026!`.
**Cambie estas contraseñas antes de salir a producción.**

## 7. Notas
- Respaldos: **Superadmin → Respaldos** (manual) y automático semanal en
  `/storage/backups/`. Esa carpeta no es accesible desde el navegador.
- Correo: cada empresa configura su propio SMTP en **Panel → Configuración**;
  si lo deja vacío se usa el SMTP de la plataforma o `mail()` del hosting.
- Registros de error: `/storage/logs/`. Nunca se muestran al visitante.
- **Fotografía**: el sistema trae imágenes industriales y láminas técnicas
  generadas por el propio tema (`php tools/generate-images.php` y
  `php tools/generate-plates.php`). Para reemplazarlas por fotografía real de
  Unsplash desde su hosting: pegue las URL en `tools/fotos.txt` y ejecute
  `php tools/fetch-photos.php`, o con su clave de la API:
  `php tools/fetch-photos.php SU_CLAVE_UNSPLASH`. El script genera JPG + WebP
  en tres tamaños y la miniatura borrosa, y guarda los créditos de autoría.
