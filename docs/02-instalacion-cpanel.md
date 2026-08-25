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

Complete como mínimo:

```php
'emisor' => [
    'nit'                    => '12345679',      // su NIT, sin guion
    'nombre'                 => 'MI EMPRESA, SOCIEDAD ANONIMA',
    'nombre_comercial'       => 'MI EMPRESA',
    'afiliacion_iva'         => 'GEN',           // GEN, PEQ, PEE, AGR, AGE, EXE
    'codigo_establecimiento' => '1',             // el que le asignó SAT
    'correo'                 => 'facturacion@miempresa.gt',
    'direccion'              => '5a. Avenida 10-50 zona 1',
    'municipio'              => 'Guatemala',
    'departamento'           => 'Guatemala',
],

'db' => [
    'driver'  => 'mysql',
    'host'    => 'localhost',
    'nombre'  => 'suusuario_fel',
    'usuario' => 'suusuario_felapp',
    'clave'   => 'la-contrasena-del-paso-1',
],
```

> Los datos del emisor deben coincidir **exactamente** con su RTU en la Agencia
> Virtual de SAT. Una coma o una tilde de diferencia hace que el certificador
> rechace el documento.

Deje `'certificador' => ['proveedor' => 'simulador']` por ahora. En el paso 7
lo cambia por el real.

Genere también una clave para la aplicación:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

y péguela en `'app' => ['clave_aplicacion' => '...']`.

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
3. Para crear el usuario administrador, ejecute en la pestaña **SQL**:

```sql
INSERT INTO fel_usuarios (usuario, clave_hash, nombre, rol, activo, creado_en)
VALUES ('admin', 'PEGUE_AQUI_EL_HASH', 'Administrador', 'admin', 1, NOW());
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

## Paso 7 — Conectar el certificador

Vea **[03 — Conectar su certificador](03-certificadores.md)**.

Hasta que no haga este paso, el sistema emite con el **simulador** y los
documentos **no tienen validez fiscal** (el sistema se lo advierte con una
cinta amarilla en cada pantalla).

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
php tests/run.php          # deben pasar las 95 pruebas
php bin/emitir_ejemplo.php # emite un documento de prueba
```

Luego entre a `https://facturacion.suempresa.gt`, ingrese con el usuario
administrador y emita una factura de prueba. Revise que:

- La representación gráfica muestra el número de autorización.
- El XML se descarga bien.
- El documento aparece en el listado como `CERTIFICADO`.

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
