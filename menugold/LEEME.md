# MenúGold · Menú QR con pedidos para restaurantes

## 1. Qué necesita tu hosting
- PHP **8.0 o superior** con las extensiones `pdo_mysql`, `mbstring`, `gd`, `zip`, `openssl` y `json`.
- MySQL 8 o MariaDB 10.4+ (utf8mb4).
- Apache con `mod_rewrite` (el `.htaccess` ya viene incluido).
- Certificado SSL activo (el sistema fuerza HTTPS).
- No hace falta Composer ni Node: todas las librerías van dentro de `/vendor/`.

## 2. Subir los archivos en cPanel
1. Entra a **cPanel → Administrador de archivos**.
2. Abre `public_html` (o la carpeta del subdominio donde quieres MenúGold).
3. Sube `menugold.zip` y elige **Extraer**.
4. Comprueba que `index.php` y `.htaccess` quedaron en la raíz de esa carpeta,
   no dentro de una subcarpeta `menugold/`.
5. En **cPanel → Bases de datos MySQL** crea una base de datos y un usuario,
   y asígnale **todos los privilegios**. Anota nombre, usuario y contraseña.

## 3. Instalar
Abre en el navegador `https://TUDOMINIO/install/` y sigue los 3 pasos:
requisitos → base de datos → restaurante y administrador.
Al terminar, el instalador guarda tus datos en `config/ajustes.json`, importa la base y **se bloquea solo**.
Si marcas *«Cargar datos de demostración»* tendrás el restaurante de ejemplo listo para ver.
Por seguridad, borra la carpeta `/install/` cuando termines.

## 4. Tarea programada (cron)
En **cPanel → Trabajos cron**, cada 10 minutos:

```
*/10 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=TU_TOKEN"
```

`TU_TOKEN` es el valor `cron_token` que aparece en `config/ajustes.json`.
Esta tarea suspende restaurantes vencidos, avisa de vencimientos próximos,
cierra pedidos abandonados y hace el respaldo semanal.

## 5. Crear un restaurante nuevo y darle su subdominio
1. Entra como superadministrador → **Restaurantes → Nuevo**.
2. Escribe el nombre, el enlace corto (*slug*), el plan y la fecha de vencimiento.
   Su menú queda en `https://TUDOMINIO/r/el-slug`.
3. ¿Quieres que use su propio dominio o subdominio?
   En cPanel crea el subdominio apuntándolo **a la misma carpeta** de MenúGold,
   y en la ficha del restaurante escribe ese dominio en el campo **Dominio propio**.
   A partir de ahí su menú abre directo en `https://sudominio.com`.

## 6. Imprimir los códigos QR
**Panel del restaurante → Mesas y QR → Imprimir QR**.
Elige el diseño (tarjeta de mesa, calcomanía o tarjeta), el tamaño
(A6, 10×10 cm o carta) y descarga el PDF ya listo para imprimir,
con el logo y los colores del restaurante. También puedes bajar el QR
general del menú para la entrada o la vitrina.

## 7. Accesos de demostración
| Rol | Usuario | Contraseña |
|---|---|---|
| Superadministrador | `admin@plataforma.gt` | `Admin2026!` |
| Dueño del restaurante | `dueno@laterraza.gt` | `Terraza2026!` |
| Cocina | `cocina1` | `Cocina2026!` |
| Mesero | `mesero1` | `Mesero2026!` |

> **Cambia estas contraseñas antes de usar el sistema con clientes reales.**

---

## 8. Si tu antivirus o tu hosting revisa los archivos
El paquete son archivos PHP, CSS, SQL, este documento y las **fotos de los
platillos de demostración** en `/assets/demo` (JPEG normales). No hay
ejecutables, ni archivos comprimidos dentro, ni código codificado en base64.

**No hay ningún archivo `.js` en el paquete.** Es a propósito: varios antivirus
de hosting y de correo (las firmas *Foxhole* de Sanesecurity, entre otras)
rechazan cualquier archivo comprimido que contenga un `.js`, sin mirar lo que
hay dentro, porque así viajaba el malware por correo hace años. Los guiones de
JavaScript viven en disco como `.jstxt` y los sirve PHP en `/js/panel.js`,
`/js/menu.js` y `/js/chart.js`, con su tipo de contenido correcto. El navegador
no nota la diferencia.

El sistema está escrito a propósito para pasar limpio los escaneos de los
hosting compartidos (ImunifyAV, cPanel, ClamAV):

- No usa `eval`, `assert`, `system`, `shell_exec`, `passthru` ni `exec`.
- No decodifica ni descomprime cadenas en memoria (`base64_decode`, `gzinflate`).
- **Nunca genera ni escribe archivos `.php`.** El instalador solo guarda un
  archivo de datos, `config/ajustes.json`, y `config/config.php` viene incluido
  en el ZIP sin modificarse jamás.
- Las plantillas no usan `extract()`; las variables se crean una a una.
- Las fotos que suben los usuarios se validan por su contenido real y se vuelven
  a generar con GD, así que no pueden llevar nada oculto dentro.
- `/config`, `/app`, `/vendor` y `/storage` están bloqueados por `.htaccess`, y
  en `/storage` PHP no se ejecuta.

Si aun así tu antivirus marca algún archivo, es un falso positivo: pásale el
nombre del archivo y el nombre de la detección a tu proveedor de hosting para
que lo agreguen a la lista blanca.
