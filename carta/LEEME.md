# MenúGold · menú QR con pedidos, para un restaurante

Sitio del restaurante + menú del comensal + panel, cocina, salón y reportes.
Una instalación por restaurante: todo lo que hay dentro es de ese restaurante.

## 1. Requisitos del hosting

PHP **8.0 o superior** con las extensiones `pdo_mysql`, `gd`, `mbstring`, `zip` y
`openssl` (en cPanel: *Select PHP Version → Extensions*). MySQL 8 o MariaDB 10.4+.
Apache con `mod_rewrite`. Certificado SSL activo. El instalador revisa todo esto
y te dice qué falta antes de continuar.

## 2. Instalación (una pantalla, medio minuto)

1. En **File Manager**, entra a `public_html`, sube `menugold.zip` y elige **Extract**.
   El contenido queda en la raíz (verás `index.php`, `app/`, `assets/`…).
2. Abre `https://TUDOMINIO/install/`.
3. Llena los datos de tu base de datos MySQL y tu cuenta. Pulsa **Instalar**.
4. **Borra la carpeta `/install`** desde el File Manager. Es obligatorio.
5. Entra a `https://TUDOMINIO/panel/entrar`.

**No necesitas crear una base de datos nueva.** MenúGold crea sus propias tablas
con el prefijo `mg_`, así que puede vivir en una base que ya uses sin tocar nada
de lo que haya ahí.

Deja marcada la casilla de **menú de ejemplo** la primera vez: te instala 35
platillos con su fotografía, 12 mesas con QR, modificadores, promociones y
cupones. Los borras o los editas cuando quieras. Tu nombre, tu correo y tu
logotipo se aplican solos; los datos de contacto del ejemplo se dejan vacíos.

Si algo falla, el detalle queda en `storage/logs/`.

## 3. Tarea programada (cron)

cPanel → **Cron Jobs** → cada 10 minutos. El instalador te muestra la línea
exacta con tu token; tiene esta forma:

```
*/10 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=XXXX"
```

Cierra llamadas al mesero olvidadas, libera mesas, purga la bitácora y crea un
**respaldo semanal automático** (descargable desde *Respaldos*).

## 4. Códigos QR de las mesas

Panel → **Mesas**. Crea las mesas de una vez con *Generar varias* y descarga el
PDF en el formato que prefieras: **tarjeta de mesa** (se dobla y se para sola),
**tarjeta de bolsillo** o **etiquetas adhesivas**. Salen con tu logotipo y tus
colores, y el código va firmado: nadie puede fabricar el enlace de una mesa a
mano.

El comensal escanea, se le abre el menú **con su mesa ya reconocida**, pide, y el
pedido entra a la pantalla de cocina en un segundo, sin recargar nada.

## 5. Fotografía

El menú de ejemplo viene **con fotografía en los 35 platillos, la portada y las
seis categorías**: imágenes propias incluidas en el paquete, no se descarga nada
de internet.

Para tus platillos, sube tus fotos desde cada platillo en el panel. Se recortan,
se comprimen a WebP con respaldo JPG en tres tamaños (480/960/1600), se les
quitan los metadatos y se genera el difuminado de carga.

## 6. Modos de servicio

En *Ajustes* eliges cómo trabaja el menú:

- **En mesa** · el comensal pide desde el QR de su mesa.
- **Para llevar** y **A domicilio** · con zonas de entrega, costo de envío y
  pedido mínimo por zona.
- **Solo catálogo** · el menú se ve pero no se toman pedidos.
- **WhatsApp** · el pedido llega armado a tu WhatsApp.

## 7. Credenciales del ejemplo

Tu cuenta de dueño es la que creaste en el instalador. Además, el menú de
ejemplo trae este personal (bórralo o cámbiale la contraseña):

| Quién | Usuario | Contraseña | PIN |
|---|---|---|---|
| Gerente | `gerente` | `Gerente2026!` | 2468 |
| Cocina | `cocina` | `Cocina2026!` | 1357 |
| Mesero | `mesero1` | `Mesero2026!` | 2222 |
| Mesero | `mesero2` | `Mesero2026!` | 3333 |

Cocina y meseros también entran con su PIN en `/panel/pin`, pensado para la
tablet del salón.

> **Cámbialas antes de abrir al público** en *Usuarios*.

## 8. Rutas útiles

| Qué | Dónde |
|---|---|
| Menú del comensal | `/` |
| Menú desde el QR de una mesa | `/mesa/CÓDIGO?k=FIRMA` |
| Seguimiento del pedido | `/pedido/TOKEN` |
| Panel | `/panel` |
| Pantalla de cocina | `/panel/cocina` |
| Salón del mesero | `/panel/mesero` |
| Acceso con PIN | `/panel/pin` |
