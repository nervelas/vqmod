# 02 — Instalación en cPanel

Guía paso a paso para dejar el sistema funcionando en un hosting compartido
con cPanel. Tiempo aproximado: 30 minutos.

## Antes de empezar

Confirme en cPanel:

- **PHP 8.1 o superior** (cPanel → *Select PHP Version* / *MultiPHP Manager*)
- Extensiones activas: `dom`, `curl`, `pdo_mysql`, `mbstring`, `openssl`
- Un **certificado SSL** activo en el dominio (cPanel → *SSL/TLS Status*).
  Con Let's Encrypt gratuito es suficiente. **No opere sin HTTPS.**

## Paso 1 — Crear la base de datos

1. cPanel → **Bases de datos MySQL**
2. *Crear nueva base de datos*: por ejemplo `fel`
   → cPanel la nombrará `suusuario_fel`
3. *Usuarios de MySQL* → crear un usuario, por ejemplo `felapp`
   → quedará como `suusuario_felapp`. Use una contraseña larga y guárdela.
4. *Agregar usuario a la base de datos* → marcar **TODOS LOS PRIVILEGIOS**

Anote los tres datos: nombre de la base, usuario y contraseña.

## Paso 2 — Subir los archivos

La estructura recomendada deja el código **fuera** de `public_html`:

```
/home/suusuario/
├── fel/                    ← todo el proyecto aquí
│   ├── bin/  config/  db/  docs/  src/  storage/  tests/
│   └── public/             ← esta carpeta es la que se publica
└── public_html/
```

Opciones para subirlo:

**Con el Administrador de archivos**: comprima el proyecto en un `.zip`,
súbalo a `/home/suusuario/`, y use *Extraer*.

**Con Terminal** (si su plan lo permite):

```bash
cd ~
git clone <este-repositorio> fel
```

## Paso 3 — Publicar solo `public/`

Hay dos formas; la primera es la buena.

**Opción A — Subdominio apuntando a `public/` (recomendada)**

1. cPanel → **Dominios** → *Crear un subdominio*
2. Subdominio: `facturacion` · Dominio: `suempresa.gt`
3. **Raíz del documento**: `/home/suusuario/fel/public`

Queda accesible en `https://facturacion.suempresa.gt`.

**Opción B — Dentro de `public_html`**

Si su plan no permite cambiar la raíz del documento, mueva el proyecto a
`public_html/fel/`. El archivo `.htaccess` de la raíz del proyecto ya bloquea
el acceso web a `config/`, `src/` y `storage/`, pero es una protección de
segunda línea: la opción A es más segura.

## Paso 4 — Configurar

Copie el archivo de ejemplo y edítelo con el Administrador de archivos:

```
config/config.example.php  →  config/config.php
```

Este archivo solo lleva la configuración de **la instalación**. Los datos de
cada empresa emisora (NIT, razón social, credenciales del certificador) se
cargan después desde la pantalla **Empresas** y se guardan cifrados en la base.

Complete como mínimo dos cosas:

```php
'db' => [
    'driver'  => 'mysql',
    'host'    => 'localhost',
    'nombre'  => 'suusuario_fel',
    'usuario' => 'suusuario_felapp',
    'clave'   => 'la-contrasena-del-paso-1',
],

'app' => [
    'nombre'           => 'Facturación FEL',
    'clave_aplicacion' => 'PEGUE-AQUI-LA-CLAVE-GENERADA',
],
```

Genere la clave de aplicación con:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

> **Guarde esa clave.** Con ella se cifran las credenciales de certificador de
> todas sus empresas. Si la pierde, hay que volver a capturarlas una por una.
> Respáldela junto con la base de datos.

Si quiere que el instalador cree ya la primera empresa, llene también la sección
`'emisor'`. Si prefiere darlas de alta desde la pantalla, déjela vacía.

## Paso 5 — Instalar las tablas

**Con Terminal:**

```bash
cd ~/fel
php bin/instalar.php
```

Le pedirá usuario, nombre y contraseña del administrador.

**Sin Terminal** (usando phpMyAdmin):

1. cPanel → **phpMyAdmin** → seleccione su base de datos
2. Pestaña **Importar** → suba `db/schema.sql` → *Continuar*
3. Para crear el administrador de la plataforma, ejecute en la pestaña **SQL**:

```sql
INSERT INTO fel_usuarios (empresa_id, usuario, clave_hash, nombre, rol, activo, creado_en)
VALUES (NULL, 'admin', 'PEGUE_AQUI_EL_HASH', 'Administrador', 'superadmin', 1, NOW());
```

El hash lo obtiene con **cPanel → Trabajos Cron** ejecutando una sola vez:

```
/usr/local/bin/php -r 'echo password_hash("SU-CONTRASEÑA", PASSWORD_BCRYPT, ["cost"=>12]);'
```

o pídalo a quien administre el servidor. **Nunca guarde la contraseña en texto plano.**

## Paso 6 — Permisos de las carpetas

La carpeta `storage/` debe ser escribible por PHP:

```bash
chmod -R 770 ~/fel/storage
```

Desde el Administrador de archivos: clic derecho sobre `storage` → *Permisos*
→ `770`, marcando *Aplicar recursivamente*.

Verifique que `config/` y `storage/` **no** sean accesibles desde el navegador.
Si abre `https://sudominio.gt/config/config.php` debe dar error 403.

## Paso 7 — Dar de alta las empresas y su certificador

Ingrese como administrador de la plataforma y vaya a **Empresas → Agregar
empresa**. Ahí carga, por cada cliente:

- Los datos del emisor, **idénticos a su RTU**.
- Las credenciales de su certificador (se guardan cifradas).
- El formato de impresión: hoja carta o ticket de 80 mm.
- El color de marca y el logo.
- El primer usuario de esa empresa.

Detalle de la contratación y las credenciales:
**[03 — Conectar el certificador](03-certificadores.md)**.
Cómo ofrecerlo a sus clientes: **[06 — Vender el servicio](06-vender-el-servicio.md)**.

Mientras una empresa siga con el certificador **simulador**, sus documentos
**no tienen validez fiscal**, y el sistema lo advierte con una cinta amarilla
en cada pantalla.

## Paso 8 — Programar el cron de contingencia

cPanel → **Trabajos Cron** → *Agregar nuevo trabajo cron*

- Configuración común: **Cada 10 minutos**
- Comando:

```
/usr/local/bin/php /home/suusuario/fel/bin/reintentar_pendientes.php >> /home/suusuario/fel/storage/logs/cron.log 2>&1
```

Verifique la ruta real de PHP en *Select PHP Version → Opciones*; en algunos
servidores es `/usr/local/bin/ea-php81` o similar.

Este cron es el que rescata los documentos que quedaron sin certificar cuando
se cayó el internet o el certificador. **No lo omita.**

## Paso 9 — Comprobar

```bash
cd ~/fel
php tests/run.php          # deben pasar las 138 pruebas
```

Luego entre a `https://facturacion.suempresa.gt`, ingrese como administrador de
la plataforma, entre a una empresa y emita una factura de prueba. Revise que:

- La representación gráfica muestra el número de autorización.
- El código QR se lee con el celular.
- El ticket de 80 mm sale bien si va a usar impresora térmica.
- El XML se descarga bien.
- El documento aparece en el listado como `CERTIFICADO`.

## Actualizar desde la versión de un solo emisor

Si ya tenía el sistema funcionando con un único emisor:

1. **Respalde la base de datos** (cPanel → *Copias de seguridad*).
2. Importe `db/migracion-002-multiempresa.sql` desde phpMyAdmin.
3. Ejecute `php bin/migrar_multiempresa.php`.

El script crea la empresa a partir de `config.php` y le asigna todos los
documentos, clientes y productos existentes. Su usuario administrador pasa a
ser administrador de la plataforma.

## Problemas frecuentes

| Síntoma | Causa habitual |
|---|---|
| "No existe el archivo de configuración" | No copió `config.example.php` a `config.php` |
| Error de conexión a la base | El usuario no está agregado a la base, o falta el prefijo `suusuario_` |
| Página en blanco | Versión de PHP menor a 8.1, o falta una extensión |
| "No se pudo crear el directorio" | Permisos de `storage/`: póngalos en `770` |
| No se guardan los XML en disco | Igual que el anterior |
| El cron no corre | Ruta de PHP incorrecta; revísela en *Select PHP Version* |
| Errores de TLS al certificar | No desactive la verificación: pida a su hosting que actualice el paquete de certificados raíz |
