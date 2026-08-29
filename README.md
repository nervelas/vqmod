# Sistema de facturación FEL — Guatemala

Plataforma de facturación electrónica en PHP para el régimen **FEL** de la SAT
de Guatemala. Genera **Documentos Tributarios Electrónicos (DTE)**, los manda a
certificar, guarda el número de autorización e imprime la representación
gráfica con su **código QR** — en hoja carta o en **ticket de 80 mm** para
impresora térmica.

Es **multi-empresa**: una sola instalación atiende a todos sus clientes. Cada
uno entra con su usuario y ve únicamente su empresa, sus clientes y sus
facturas. Pensado para venderse como servicio mensual.

Corre en **hosting cPanel** con PHP 8.0+ y MySQL, **sin Composer ni
dependencias externas**.

---

## Lo primero: cómo funciona FEL de verdad

> **En Guatemala nadie se conecta directo a la SAT para certificar facturas.**
> El régimen FEL exige pasar por un **Certificador de Documentos Tributarios
> Electrónicos** autorizado por SAT (INFILE, Digifact, Guatefacturas,
> Megaprint, Certifika…). Usted arma el XML → el certificador lo firma, lo
> valida, le asigna el **número de autorización (UUID)** y lo transmite a SAT.

Cuando alguien factura desde el portal gratuito de SAT, ese portal hace
internamente lo mismo. Al pasar a sistema propio, cada empresa contrata un
certificador comercial. Cuesta centavos por documento.

Este sistema cubre **todo el lado del contribuyente**: captura, cálculo de IVA,
XML conforme al esquema de SAT, control de folios, anulaciones, contingencia y
resguardo. Lo único que hay que poner es el contrato con el certificador.

Lo que **no** hace, para que no haya sorpresas:

- No habilita a nadie como emisor FEL ante SAT — eso se hace en la Agencia Virtual.
- No reemplaza al contador ni decide qué régimen o frases aplican.
- No transmite a SAT por su cuenta: siempre pasa por el certificador.

Antes del primer documento real, lea
**[docs/04-cumplimiento-sat.md](docs/04-cumplimiento-sat.md)**.
Para venderlo como servicio, **[docs/06-vender-el-servicio.md](docs/06-vender-el-servicio.md)**.

---

## Qué incluye

**Multi-empresa**

- Alta de empresas desde la interfaz: datos de emisor, certificador, formato de
  impresión, color de marca y logo.
- Credenciales de cada certificador **cifradas con AES-256-GCM** en la base.
- Tres roles: administrador de la plataforma (usted), administrador de empresa
  y operador.
- Aislamiento verificado con pruebas: una empresa no puede leer documentos,
  clientes ni XML de otra, ni por interfaz ni por consulta directa.
- El emisor del DTE lo impone siempre la empresa activa: nunca se toma del
  formulario.
- Panel de uso por empresa y período, para facturar el servicio.

**Documentos**

- 14 tipos de DTE: `FACT`, `FCAM`, `FPEQ`, `FCAP`, `FESP`, `NABN`, `RDON`,
  `RECI`, `NDEB`, `NCRE`, `FACA`, `FCCA`, `FAPE`, `FAAE`.
- Desglose de IVA hacia adentro (el precio de lista guatemalteco ya lo incluye).
- Regímenes sin crédito fiscal (pequeño contribuyente, agropecuario): sin
  desglose y con la frase que corresponde.
- Notas de crédito y débito con el complemento de referencia al documento origen.
- Factura cambiaria con complemento de abonos.
- Adenda para datos no fiscales.
- Anulación de DTE certificados, con aviso cuando se sale de plazo.

**Impresión**

- **Hoja carta**: diseño limpio, con el color y el logo de cada empresa.
- **Ticket 80 mm**: formato de rollo para impresora térmica de punto de venta,
  con la estructura de una factura FEL.
- **Código QR** en ambos formatos, generado en PHP puro — sin librerías,
  sin llamadas a servicios externos, sin extensiones gráficas.

**Operación**

- Panel, emisión, listado con filtros, detalle, clientes y productos.
- Descarga del XML certificado.
- **Modo contingencia**: si se cae el internet o el certificador, el documento
  queda guardado y un cron lo reintenta —recorriendo todas las empresas— sin
  duplicar folios.
- Bitácora de cada intercambio con el certificador.
- Resumen mensual de base gravable e IVA para conciliar la declaración.

**Validaciones antes de gastar un folio**

- NIT guatemalteco con dígito verificador (módulo 11).
- CUI/DPI con dígito verificador.
- Aviso al pasar el monto en que SAT espera identificar al comprador.
- Estructura completa del documento contra el catálogo de SAT.

**Certificadores**

- `simulador` — desarrollo, capacitación y demostraciones. Sin validez fiscal.
- `infile` — adaptador concreto (firma + certificación).
- Adaptador **REST genérico configurable**: conecta cualquier otro certificador
  declarando URL, cabeceras y formato desde la pantalla de la empresa, sin
  tocar código.

---

## Instalación rápida

```bash
git clone <este-repositorio> fel
cd fel

cp config/config.example.php config/config.php
nano config/config.php          # base de datos y clave de aplicación

php bin/instalar.php            # crea tablas y el administrador de la plataforma
php tests/run.php               # 138 pruebas, todas deben pasar
```

Apunte su dominio o subdominio a la carpeta **`public/`**, ingrese como
administrador de la plataforma y dé de alta a sus clientes desde **Empresas**.

Paso a paso para cPanel: **[docs/02-instalacion-cpanel.md](docs/02-instalacion-cpanel.md)**.

**¿Ya tenía la versión de un solo emisor?**
Respalde la base, importe `db/migracion-002-multiempresa.sql` y ejecute
`php bin/migrar_multiempresa.php`. Sus documentos quedan asignados a la empresa
creada desde `config.php`.

---

## Documentación

| Documento | Contenido |
|---|---|
| [01 — Cómo funciona FEL](docs/01-como-funciona-fel.md) | El circuito emisor → certificador → SAT, y qué hace cada quien |
| [02 — Instalación en cPanel](docs/02-instalacion-cpanel.md) | Base de datos, archivos, dominio, cron, permisos |
| [03 — Conectar el certificador](docs/03-certificadores.md) | Contratación, credenciales, configuración y pruebas |
| [04 — Cumplimiento ante SAT](docs/04-cumplimiento-sat.md) | Lista de verificación para no tener problemas |
| [05 — Operación diaria](docs/05-operacion-diaria.md) | Emitir, anular, notas de crédito, contingencia, respaldos |
| [06 — Vender el servicio](docs/06-vender-el-servicio.md) | Cómo dar de alta clientes, qué cobrar, cómo presentarlo |

**[Manual de uso y configuración (PDF)](docs/manual/Manual-Facturacion-FEL.pdf)** — 17 páginas
con capturas reales de cada pantalla: instalación, alta de empresas, emisión, impresión,
anulaciones, contingencia y problemas frecuentes. Se puede entregar tal cual a los clientes.
Para regenerarlo tras cambiar la interfaz, vea `docs/manual/fuente/`.

---

## Estructura

```
bin/                 Comandos: instalar, migrar, usuarios, cron, ejemplo
config/              Configuración de la instalación (config.php NO se versiona)
db/                  Esquema MySQL, variante SQLite y migraciones
docs/                Documentación
public/              Único directorio expuesto en el navegador
  index.php          Controlador frontal
  views/             Plantillas
  assets/            CSS y JS
src/
  Core/              Config, base de datos, cifrado, bitácora, validadores GT
  Plataforma/        Empresa emisora
  Dte/               Modelo del documento, catálogos, cálculo, generación de XML
  Certificador/      Adaptadores (simulador, INFILE, REST genérico)
  Servicio/          Emisión, anulación, contingencia, almacenamiento
  Repositorio/       Persistencia (siempre acotada a una empresa)
  Presentacion/      Representación gráfica y generador de códigos QR
  Web/               Sesión, contexto de empresa, vistas, mensajes
storage/             XML emitidos y bitácoras (fuera del alcance del navegador)
tests/               Pruebas automatizadas sin dependencias
```

---

## Pruebas

```bash
php tests/run.php
```

138 pruebas con SQLite y el certificador simulado: **no tocan la red ni gastan
folios**. Cubren validación de NIT y CUI, cálculo de IVA, estructura del XML,
notas de crédito, factura cambiaria, emisión completa, anulación, contingencia,
respuestas del certificador, cifrado de credenciales, generación de códigos QR
y **aislamiento entre empresas**.

El generador de QR se validó además contra implementaciones de referencia:
1.344 matrices comparadas módulo a módulo (versiones 1–15, los cuatro niveles
de corrección, las ocho máscaras) y 230 códigos decodificados con ZXing.

---

## Requisitos

- **PHP 8.0 o superior** con `dom`, `curl`, `pdo_mysql`, `mbstring`, `openssl`

  El código no usa nada posterior a PHP 8.0. Puede comprobarlo en cualquier
  momento con `php bin/verificar_php.php 8.0`, que analiza todos los archivos
  con el tokenizador de PHP y avisa si se coló sintaxis o alguna función de una
  versión más nueva. La suite de pruebas lo ejecuta en cada corrida.
- MySQL 5.7+ / MariaDB 10.3+
- HTTPS en el dominio (obligatorio: se manejan datos fiscales y credenciales)
- Cada empresa: contrato con un certificador autorizado y habilitación como
  emisor FEL en la Agencia Virtual de SAT

---

## Seguridad

- Las credenciales de certificador se guardan **cifradas** (AES-256-GCM) con la
  clave de `app.clave_aplicacion`; el cifrado es autenticado, así que un
  registro alterado se detecta en lugar de descifrarse mal.
- Respalde `app.clave_aplicacion` junto con la base: sin ella no se recuperan
  las credenciales guardadas.
- `config/config.php` nunca se versiona (ya está en `.gitignore`) y debe quedar
  fuera de `public_html`.
- Solo `public/` se expone al navegador; el resto queda bloqueado por `.htaccess`.
- Contraseñas con bcrypt, sesiones con cookies `HttpOnly` y `SameSite`,
  protección CSRF en todos los formularios, consultas preparadas en todo el
  acceso a datos.
- Todos los repositorios exigen el id de empresa al construirse: no existe una
  consulta que pueda olvidarse del filtro.
- La bitácora enmascara llaves y tokens.
- La verificación TLS hacia el certificador nunca debe desactivarse.
