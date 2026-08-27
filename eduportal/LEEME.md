# EduPortal · Sistema Integral de Gestión para Colegios

## 1. Requisitos del hosting
- **PHP 8.0 o superior** con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json` y `zlib`.
  Se recomienda además `gd` (iconos de la app móvil, carné con QR y miniaturas).
- **MySQL 8 / MariaDB 10.4 o superior**, con juego de caracteres `utf8mb4`.
- **Apache con `mod_rewrite`** (estándar en cPanel) y, de preferencia, certificado SSL activo.
- No requiere Node ni Composer: todas las librerías (PDF, correo SMTP, Excel y Chart.js) vienen incluidas.

## 2. Subir e instalar en cPanel
1. Entre a **cPanel → Administrador de archivos** y abra `public_html` (o la carpeta del subdominio).
2. Suba `eduportal.zip`, selecciónelo y elija **Extraer**. Los archivos deben quedar en la raíz,
   de modo que `index.php` esté directamente dentro de `public_html`.
3. En **cPanel → Bases de datos MySQL** cree una base y un usuario, y asígnele **todos los privilegios**.
4. Abra `https://SUDOMINIO/install/` y siga el asistente de 3 pasos:
   **verificar requisitos → datos de la base → crear administrador**.
   Marque *"Cargar datos de demostración"* si desea probar el sistema con información de ejemplo.
5. Al terminar, el instalador crea `config/config.php` y se bloquea solo (`install/.lock`).
   Por seguridad, elimine la carpeta `/install` cuando ya no la necesite.

Si alguna carpeta aparece sin permiso de escritura, asígnele **755** desde el administrador de archivos
(`config`, `storage` y sus subcarpetas).

## 3. Tarea programada (cron)
En **cPanel → Trabajos cron**, agregue uno **cada 15 minutos** con este comando
(el token aparece en *Configuración → Respaldo* y al finalizar la instalación):

```
*/15 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=XXXX"
```

El cron recalcula la mora, envía los recordatorios de pago (3 días antes del vencimiento, el día
del vencimiento y cada 7 días en mora) y genera el respaldo semanal automático.

## 4. Cobros mensuales
La colegiatura es **mensual**. En *Cobranza → Generar cargos* elija un mes para el cobro corriente
o un **rango** (por ejemplo *Enero a Octubre*) para dejar preparado todo el ciclo escolar de una vez.
El proceso no duplica cargos: si un mes ya fue generado, simplemente lo omite. Cada mes tiene su
propia fecha de vencimiento, su mora y su recibo independiente.

## 5. Credenciales de la demostración
| Rol | Usuario | Contraseña |
|---|---|---|
| Administración | `admin@colegio.gt` | `Admin2026!` |
| Secretaría | `secretaria@colegio.gt` | `Secre2026!` |
| Docente | `docente@colegio.gt` | `Docente2026!` |
| Padre / encargado | `padre@colegio.gt` | `Padre2026!` |

**Cambie estas contraseñas antes de usar el sistema en producción**
(*Usuarios y accesos* → restablecer, o desde *Mi perfil*).

## 6. Cambiar el tema y el logo
En **Configuración del colegio** puede elegir entre 9 temas premium (Marino y Oro, Esmeralda, Borgoña,
Grafito, Azul Real, Verde Bosque, Terracota, Púrpura, Negro y Oro) o definir un color de acento propio.
Al cargar el **logo** se regeneran automáticamente los iconos de la aplicación móvil; también puede
forzarlo con el botón *"Regenerar iconos de la aplicación móvil"*. Cada usuario puede activar el
**modo oscuro** desde la barra superior o desde *Mi perfil*.

## 7. Instalación en el móvil
No hay botón ni mensaje de descarga: cuando el sitio se sirve por **HTTPS**, Chrome y Edge en Android
muestran su propio aviso de instalación al cumplirse los criterios de PWA. En iPhone y iPad se usa
**Compartir → Añadir a pantalla de inicio**.

## 8. Respaldos
*Configuración → Respaldo* permite descargar la base en un clic (`.sql.gz`). El respaldo automático
semanal se guarda en `/storage/backups/`, una carpeta sin acceso público. Se conservan los 12 más recientes.
