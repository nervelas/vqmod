# ResidencialPro — Administración Integral de Condominios

Sistema completo de cuotas, morosidad, visitas, reservas y comunicación para residenciales.
Instalación por carga de archivos en cPanel: **no requiere Composer, Node ni acceso SSH**.

## 1. Requisitos del hosting

| Requisito | Mínimo |
|---|---|
| PHP | 8.0 o superior (8.1+ recomendado) |
| Extensiones | `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `curl`, `fileinfo` |
| Base de datos | MySQL 8.0 o MariaDB 10.4+ (utf8mb4) |
| Servidor web | Apache con `mod_rewrite` y `AllowOverride All` |
| Certificado | HTTPS obligatorio (Let's Encrypt de cPanel sirve) |
| Espacio | 120 MB libres |

## 2. Subida en cPanel

1. Entre a **cPanel → Administrador de archivos**.
2. Abra `public_html` (dominio principal) o la carpeta del subdominio, por ejemplo `public_html/portal`.
3. Suba `residencialpro.zip` y elija **Extraer**. El contenido debe quedar en la raíz de esa carpeta:
   `index.php`, `app/`, `assets/`, `install/`, etc. (no dentro de otra subcarpeta).
4. En **cPanel → Bases de datos MySQL** cree una base y un usuario, asígnele **todos los privilegios**
   y anote nombre, usuario y contraseña.

## 3. Instalador web

Visite `https://SUDOMINIO/install/` y siga los tres pasos:

1. **Requisitos**: verifica versión de PHP, extensiones y permisos de escritura.
2. **Base de datos**: datos de la conexión que acaba de crear. Puede marcar *Cargar datos de
   demostración* para ver el sistema con un residencial de ejemplo ya poblado.
3. **Condominio y administrador**: nombre, NIT, dirección y tema visual del residencial, más su
   cuenta de administrador.

Al terminar se crea `config/config.php`, se importa la base y el instalador **se bloquea solo**
(`install/.lock`). La pantalla final muestra, una única vez, la **línea del cron** y las **llaves push**:
cópielas antes de salir. Después borre la carpeta `install/` desde el administrador de archivos.

## 4. Tareas automáticas (cron)

En **cPanel → Trabajos cron**, agregue uno cada 15 minutos con la línea que le dio el instalador:

```
*/15 * * * * curl -s "https://SUDOMINIO/cron/run.php?token=XXXX" >/dev/null 2>&1
```

Ejecuta la mora diaria, la generación mensual de cargos, los recordatorios de cobro, el escalamiento
de morosidad (carta a los 60 días, restricción en garita a los 90), el envío de correos, el cierre de
votaciones y el respaldo semanal en `storage/backups/`.

## 5. Notificaciones push y pago en línea

- **Push**: las llaves VAPID se generan durante la instalación. En **Ajustes → Notificaciones y respaldo** puede
  verlas o regenerarlas. El aviso de "instalar la aplicación" lo muestra el navegador por sí solo
  cuando se cumplen los criterios PWA (HTTPS, manifiesto e íconos); el sistema no lo interviene.
- **Pago en línea**: en **Ajustes → Cobros y mora** pegue el enlace de su pasarela (Visanet, Recurrente, PayPal…).
  Si el campo queda vacío, el botón simplemente no aparece en el portal del residente.
- **Correo**: configure el SMTP de su hosting en **Ajustes → Correo y WhatsApp** y use *Enviar prueba*.

## 6. Credenciales de demostración

Solo si cargó los datos de demostración. **Cámbielas o elimine esas cuentas antes de usar el sistema en producción.**

| Perfil | Usuario | Contraseña |
|---|---|---|
| Administrador | `admin@residencial.gt` | `Admin2026!` |
| Garita | `garita1` | `Garita2026!` |
| Residente | `casa12@residencial.gt` | `Casa2026!` |

## 7. Imagen del sitio

- **Fotografías.** `assets/img/sitio/` trae fotografías de muestra, ya recortadas
  y con una gradación común. **Sustitúyalas por las de su residencial** antes de
  publicar, desde **Sitio público → Galería** y **→ Amenidades**. La primera imagen
  de la galería reemplaza automáticamente la fotografía de portada.
- **Temas.** En **Ajustes → Identidad** hay nueve paletas (Petróleo y Barro,
  Pizarra y Cobre, Índigo y Arena, Bosque y Terracota, Borgoña y Lino, Basalto y
  Ámbar, Oliva y Hueso, Tinta y Cobalto, Océano y Coral) y modo oscuro por usuario.
- **Tipografías.** Fraunces, Archivo e IBM Plex Mono van incluidas localmente bajo
  licencia SIL OFL; no se consulta ningún servicio externo.
- El pie del sitio público lleva un pequeño crédito enlazado a deerflow.tech.
  Si no lo quiere, bórrelo de `app/Views/layouts/publico.php` (busque `class="firma"`).

## 8. Notas

- Las librerías de PDF, correo SMTP, Excel, códigos QR y gráficas son propias y van
  incluidas en `vendor/` y `assets/vendor/`: no hay dependencias externas ni CDN
  que puedan fallar.
- Las carpetas `config/`, `storage/`, `vendor/` y `app/` están bloqueadas por
  `.htaccess`. No las mueva ni cambie sus permisos.
- Respaldos manuales: **Ajustes → Notificaciones y respaldo**, o en **Respaldos**
  desde el menú de administración.
