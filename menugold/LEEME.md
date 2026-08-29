# MenúGold · menú digital QR con pedidos

Sistema multi-restaurante: sitio de venta + menú del comensal + panel, cocina y reportes.

## 1. Requisitos del hosting

PHP **8.0 o superior** con las extensiones `pdo_mysql`, `gd`, `mbstring`, `zip` y `openssl`
(en cPanel: *Select PHP Version → Extensions*). MySQL 8 o MariaDB 10.4+. Apache con
`mod_rewrite`. Certificado SSL activo. El propio instalador revisa todo esto y te dice
qué falta antes de continuar.

## 2. Instalación (10 minutos)

1. En cPanel → **MySQL Databases**, crea una base de datos y un usuario, y asígnale
   *ALL PRIVILEGES*. Anota nombre, usuario y contraseña.
2. En **File Manager**, entra a `public_html`, sube `menugold.zip` y elige **Extract**.
   El contenido queda directamente en la raíz (verás `index.php`, `app/`, `assets/`…).
3. Abre `https://TUDOMINIO/install/` y sigue los tres pasos: revisión del servidor,
   base de datos y tu cuenta de administrador. Deja marcada la casilla de
   **datos de demostración** la primera vez: te instala dos restaurantes de ejemplo.
4. Cuando termine, **borra la carpeta `/install`** desde el File Manager. Es obligatorio.
5. Entra a `https://TUDOMINIO/panel/entrar`.

Si algo falla, el detalle queda en `storage/logs/`.

## 3. Tarea programada (cron)

cPanel → **Cron Jobs** → cada 10 minutos. El instalador te muestra la línea exacta
con tu token; tiene esta forma:

```
*/10 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=XXXX"
```

Cierra llamadas al mesero olvidadas, libera mesas, suspende planes vencidos, purga la
bitácora y crea un **respaldo semanal automático** (descargable desde *Plataforma →
Respaldos*).

## 4. Crear restaurantes

En **Consola de la plataforma → Restaurantes → Nuevo**: nombre, dirección web, plan y
el usuario del dueño. Su menú queda publicado en `https://TUDOMINIO/r/su-nombre`.
Desde ahí puedes *Entrar a su panel* para configurarlo tú mismo.

**¿Subdominio propio para un cliente?** En cPanel → *Subdomains*, crea el subdominio y
apunta su *Document Root* a la **misma carpeta** `public_html`. No hay que tocar código:
el sistema detecta solo el dominio, la carpeta y si hay HTTPS.

## 5. Códigos QR de las mesas

Panel → **Mesas y QR**. Crea las mesas de una vez con *Generar varias* y descarga el PDF
en el formato que prefieras: **tarjeta de mesa** (se dobla y se para sola), **tarjeta de
bolsillo** o **etiquetas adhesivas**. Salen con tu logo y tus colores, y el código va
firmado: nadie puede fabricar el enlace de una mesa a mano.

## 6. Fotografía

La demostración trae imágenes ambientales generadas por el propio sistema. Sustitúyelas
por tus fotos desde **Menú → cada platillo → Fotografía** (se recortan, comprimen a WebP
en tres tamaños y se les quitan los metadatos solas), o importa una carpeta entera:

```
php tools/importar-fotos.php --carpeta=/home/usuario/fotos --restaurante=1
```

## 7. Credenciales de la demostración

| Quién | Usuario | Contraseña |
|---|---|---|
| Superadministrador | `admin@plataforma.gt` | `Admin2026!` |
| Dueño (Brasa Negra) | `dueno@brasanegra.gt` | `Brasa2026!` |
| Cocina | `cocina1` | `Cocina2026!` · PIN `1357` |
| Mesero | `mesero1` | `Mesero2026!` · PIN `2222` |
| Dueño (Café Central) | `dueno@cafecentral.gt` | `Cafe2026!` |

Cocina y meseros también entran con su PIN en `/panel/pin`, pensado para la tablet del salón.

> **Cámbialas antes de salir a producción** en *Panel → Usuarios*.

## 8. Rutas útiles

`/` sitio de venta · `/r/{restaurante}` menú · `/panel` panel del restaurante ·
`/panel/cocina` pantalla de cocina · `/panel/mesero` salón · `/super` consola de la plataforma.
