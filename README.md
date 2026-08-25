# Sistema de facturación FEL — Guatemala

Sistema de facturación electrónica en PHP que genera **Documentos Tributarios
Electrónicos (DTE)** del régimen **FEL** de la SAT de Guatemala: arma el XML,
lo manda a firmar y certificar, guarda el número de autorización, imprime la
representación gráfica y maneja anulaciones y notas de crédito.

Está hecho para correr en **hosting cPanel** con PHP 8.1+ y MySQL, sin Composer
ni dependencias externas.

---

## Respuesta corta a la pregunta de fondo

**Sí se puede**, y este sistema lo hace. Pero hay un punto que ningún sistema
puede saltarse, y conviene tenerlo claro desde el principio:

> **En Guatemala nadie se conecta directo a la SAT para certificar facturas.**
> El régimen FEL exige pasar por un **Certificador de Documentos Tributarios
> Electrónicos** autorizado por SAT (INFILE, Digifact, Guatefacturas, Megaprint,
> Certifika, entre otros). Usted arma el XML → el certificador lo firma, lo
> valida, le asigna el **número de autorización (UUID)** y lo transmite a SAT.

O sea: este sistema le da **todo el lado suyo** de la cadena (captura, cálculo
de IVA, XML conforme al esquema de SAT, control de folios, anulaciones,
contingencia, resguardo). Lo único que usted tiene que poner de su parte es un
**contrato con un certificador**, que es de bajo costo y se paga por documento
o por paquete mensual.

Con eso, las facturas que emita **son autorizadas y válidas ante SAT**, igual
que las que hace hoy desde el portal gratuito. La diferencia es que ya no las
captura a mano: salen de su propio sistema, con su catálogo de clientes y
productos, y quedan guardadas en su base de datos.

Lo que **no** hace este sistema, para que no haya sorpresas:

- No lo habilita como emisor FEL ante SAT — eso se hace en la Agencia Virtual.
- No reemplaza a su contador ni le dice qué régimen o qué frases le aplican.
- No transmite a SAT por su cuenta: siempre pasa por el certificador.

Lea **[docs/04-cumplimiento-sat.md](docs/04-cumplimiento-sat.md)** antes de
emitir el primer documento real. Es la lista de todo lo que debe estar en orden
para no tener problemas con SAT.

---

## Qué incluye

**Documentos**

- Tipos de DTE: `FACT`, `FCAM`, `FPEQ`, `FCAP`, `FESP`, `NABN`, `RDON`, `RECI`,
  `NDEB`, `NCRE`, `FACA`, `FCCA`, `FAPE`, `FAAE`.
- Desglose de IVA hacia adentro (el precio de lista guatemalteco ya lo incluye).
- Regímenes sin crédito fiscal (pequeño contribuyente, agropecuario): sin
  desglose de IVA y con la frase que corresponde.
- Notas de crédito y débito con el complemento de referencia al documento origen.
- Factura cambiaria con complemento de abonos.
- Adenda para datos no fiscales (número de pedido, vendedor, tipo de cambio).
- Anulación de DTE certificados, con aviso cuando se sale de plazo.

**Operación**

- Interfaz web: panel, emisión, listado con filtros, detalle, clientes,
  productos y ajustes.
- Representación gráfica lista para imprimir o guardar como PDF desde el
  navegador, con todos los campos que SAT exige.
- Descarga del XML certificado.
- **Modo contingencia**: si se cae el internet o el certificador, el documento
  queda guardado y se reintenta solo con un cron, sin duplicar folios.
- Bitácora de cada intercambio con el certificador (respaldo ante una revisión).
- Resumen mensual de base gravable e IVA para conciliar la declaración.

**Validaciones antes de gastar un folio**

- NIT guatemalteco con dígito verificador (módulo 11).
- CUI/DPI con dígito verificador.
- Aviso cuando una venta a Consumidor Final pasa del monto en que SAT espera
  que se identifique al comprador.
- Estructura completa del documento contra el catálogo de SAT.

**Certificadores**

- `simulador` — para desarrollo, capacitación y pruebas. Sin validez fiscal.
- `infile` — adaptador concreto (firma + certificación).
- Adaptador **REST genérico configurable**: conecta cualquier otro certificador
  declarando URL, cabeceras y formato en `config/config.php`, sin tocar código.

---

## Instalación rápida

```bash
git clone <este-repositorio> fel
cd fel

cp config/config.example.php config/config.php
nano config/config.php          # datos del emisor, base de datos, certificador

php bin/instalar.php            # crea tablas y el usuario administrador
php tests/run.php               # 95 pruebas, todas deben pasar
php bin/emitir_ejemplo.php      # emite un documento de prueba
```

Después apunte su dominio o subdominio a la carpeta **`public/`**.

Para cPanel paso a paso: **[docs/02-instalacion-cpanel.md](docs/02-instalacion-cpanel.md)**.

---

## Documentación

| Documento | Contenido |
|---|---|
| [01 — Cómo funciona FEL](docs/01-como-funciona-fel.md) | El circuito emisor → certificador → SAT, y qué hace cada quien |
| [02 — Instalación en cPanel](docs/02-instalacion-cpanel.md) | Base de datos, subida de archivos, dominio, cron, permisos |
| [03 — Conectar su certificador](docs/03-certificadores.md) | Contratación, credenciales, configuración y pruebas |
| [04 — Cumplimiento ante SAT](docs/04-cumplimiento-sat.md) | Lista de verificación para no tener problemas |
| [05 — Operación diaria](docs/05-operacion-diaria.md) | Emitir, anular, notas de crédito, contingencia, respaldos |

---

## Estructura

```
bin/                 Comandos: instalar, usuarios, cron de contingencia, ejemplo
config/              Configuración (config.php NO se versiona)
db/                  Esquema MySQL y variante SQLite para pruebas
docs/                Documentación
public/              Único directorio expuesto en el navegador
  index.php          Controlador frontal
  views/             Plantillas
  assets/            CSS y JS
src/
  Core/              Config, base de datos, bitácora, dinero, validadores GT
  Dte/               Modelo del documento, catálogos, cálculo, generación de XML
  Certificador/      Adaptadores (simulador, INFILE, REST genérico)
  Servicio/          Emisión, anulación, contingencia, almacenamiento
  Repositorio/       Persistencia
  Presentacion/      Representación gráfica
  Web/               Sesión, vistas, mensajes
storage/             XML emitidos, bitácoras (fuera del alcance del navegador)
tests/               Pruebas automatizadas sin dependencias
```

---

## Pruebas

```bash
php tests/run.php
```

Usan SQLite en memoria y el certificador simulado: **no tocan la red ni gastan
folios**. Cubren validación de NIT y CUI, cálculo de IVA, estructura del XML,
notas de crédito, factura cambiaria, emisión completa, anulación, contingencia
y reintentos, interpretación de respuestas del certificador y repositorios.

---

## Requisitos

- PHP 8.1 o superior con `dom`, `curl`, `pdo_mysql`, `mbstring`, `openssl`
- MySQL 5.7+ / MariaDB 10.3+
- HTTPS en el dominio (obligatorio: se manejan datos fiscales y credenciales)
- Contrato con un certificador autorizado por SAT
- Estar habilitado como emisor FEL en la Agencia Virtual de SAT

---

## Seguridad

- `config/config.php` guarda llaves de firma y credenciales de API: **nunca**
  se versiona (ya está en `.gitignore`) y debe quedar fuera de `public_html`.
- Solo `public/` se expone al navegador; el resto queda bloqueado por `.htaccess`.
- Contraseñas con bcrypt, sesiones con cookies `HttpOnly` y `SameSite`,
  protección CSRF en todos los formularios, consultas preparadas en todo el
  acceso a datos.
- La bitácora enmascara llaves y tokens.
- La verificación TLS hacia el certificador nunca debe desactivarse.
