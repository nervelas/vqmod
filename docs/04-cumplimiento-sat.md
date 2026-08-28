# 04 — Cumplimiento ante SAT

Esta es la lista de lo que debe estar en orden para facturar con sistema propio
**sin tener problemas con SAT**. Recórrala completa antes de emitir el primer
documento real.

> Esta guía es orientación técnica sobre cómo opera el sistema, **no asesoría
> tributaria**. Las obligaciones concretas de cada empresa dependen de su
> régimen, su actividad y su situación. Consúltelas con su contador o su asesor
> fiscal.
>
> Si atiende a varias empresas desde una sola instalación, esta lista se recorre
> **una vez por empresa**: cada contribuyente tiene su propia habilitación ante
> SAT y su propio contrato con un certificador.

---

## A. Antes de emitir el primer documento

### 1. Estar habilitado como emisor FEL

En la **Agencia Virtual de SAT** (portal del contribuyente):

- [ ] Su RTU está actualizado y activo.
- [ ] Está **afiliado al régimen FEL** como emisor.
- [ ] Tiene habilitado **cada establecimiento** desde el que va a facturar, con
      su código. Ese código va en `codigo_establecimiento`.
- [ ] Tiene declarados los **tipos de documento** que va a emitir.

Este trámite no lo hace ningún sistema por usted: se hace en la Agencia Virtual.

### 2. Contrato con un certificador autorizado

- [ ] Contrato vigente con un certificador de la lista oficial de SAT.
- [ ] Credenciales de producción en su poder.
- [ ] Sabe a qué teléfono o correo escribir cuando algo falle. **Anótelo donde
      lo vea su gente de facturación**, no solo usted.

### 3. Datos del emisor idénticos al RTU

- [ ] NIT sin guion, con dígito verificador correcto.
- [ ] Razón social **carácter por carácter** igual al RTU (comas, `S.A.`, tildes).
- [ ] Nombre comercial registrado.
- [ ] Afiliación de IVA correcta (`GEN`, `PEQ`, `PEE`, `AGR`, `AGE`, `EXE`).
- [ ] Dirección del establecimiento.

Una diferencia mínima aquí es la causa número uno de rechazos al arrancar.

### 4. Frases del régimen

- [ ] Confirmó con su contador qué frases le corresponden.
- [ ] Verificó los pares `TipoFrase` / `CodigoEscenario` contra el catálogo
      vigente del anexo técnico de SAT.
- [ ] Si su caso no está en `src/Dte/Catalogos.php`, lo agregó ahí.

El sistema propone frases según su afiliación de IVA, pero **la
responsabilidad de que sean las correctas es del contribuyente**.

### 5. Pruebas de punta a punta

- [ ] `php tests/run.php` pasa completo.
- [ ] Emitió documentos en el **ambiente de pruebas del certificador**, no solo
      con el simulador.
- [ ] Emitió una factura real de monto bajo y **la encontró en el verificador
      público de SAT**.

---

## B. En cada documento que emita

El sistema valida esto automáticamente antes de enviar nada al certificador,
para no gastar folios en documentos que van a ser rechazados:

| Validación | Qué hace |
|---|---|
| NIT del receptor | Verifica el dígito verificador (módulo 11) |
| CUI/DPI | Verifica el dígito verificador de 13 dígitos |
| Consumidor final | Avisa al pasar el monto en que SAT espera identificar al comprador |
| Tipo de DTE | Debe existir en el catálogo |
| Frases | Al menos una, obligatoria |
| Notas de crédito/débito | Exigen referencia al documento origen |
| Líneas | Cantidad > 0, descuento no mayor al precio, unidad de medida válida |
| Moneda extranjera | Exige tipo de cambio |

**El límite de consumidor final** se configura en
`reglas.limite_consumidor_final`. Viene en Q2,500.00 como referencia habitual,
pero **verifique el monto vigente con su contador** y ajústelo. Es un aviso,
no un bloqueo.

---

## C. Conservación de los documentos

SAT puede requerir sus DTE durante el plazo de prescripción (cuatro años,
ampliable en ciertos supuestos). El sistema los guarda por partida doble:

- **En la base de datos**: `fel_documentos.xml_certificado`.
- **En archivo**: `storage/xml/AAAA/MM/TIPO-identificador-certificado.xml`.

Además queda la **bitácora** (`fel_bitacora`) con cada intercambio con el
certificador: cuándo se envió, qué respondió, si hubo error. Es el respaldo que
se presenta si alguien cuestiona un documento.

**Su responsabilidad:**

- [ ] Respaldo automático de la base de datos (cPanel → *Copias de seguridad*).
- [ ] Respaldo periódico de `storage/xml/` fuera del servidor.
- [ ] Probó **restaurar** un respaldo al menos una vez. Un respaldo que nunca se
      restauró no es un respaldo.

---

## D. Anulaciones y correcciones

- [ ] Su gente sabe la diferencia entre **anular** y emitir **nota de crédito**.
- [ ] Configuró `reglas.dias_maximos_anulacion` según el plazo que le aplique.
- [ ] Cada anulación lleva un motivo real y descriptivo, no "error".

Recuerde: una anulación fuera de plazo la rechaza el certificador. En ese caso
el instrumento correcto es la nota de crédito (`NCRE`), que este sistema emite.

---

## E. Contingencia

- [ ] El cron `bin/reintentar_pendientes.php` está programado y corriendo.
- [ ] Alguien revisa el panel: la sección de contingencia muestra los documentos
      pendientes.
- [ ] Su gente sabe qué hacer si el certificador está caído más de unas horas.

El sistema nunca pierde un documento por falta de conexión: lo guarda y lo
reintenta. Pero **si el cron no está corriendo, nadie lo reintenta**.

Distinga siempre:

- **`PENDIENTE`** → falló la comunicación. Se reintenta solo.
- **`RECHAZADO`** → el certificador rechazó el contenido. Hay que corregir y
  emitir uno nuevo. Reintentarlo solo repetiría el error.

---

## F. Seguridad

- [ ] El dominio tiene HTTPS activo y forzado.
- [ ] `config/config.php` está fuera de `public_html` o bloqueado por `.htaccess`.
- [ ] `config/config.php` **no** está en ningún repositorio de código.
- [ ] `app.clave_aplicacion` está respaldada en un lugar seguro: con ella se
      descifran las credenciales de certificador de todas las empresas.
- [ ] Cada empresa tiene sus propios usuarios; nadie comparte cuenta entre
      empresas distintas.
- [ ] Cada persona tiene su propio usuario: cada documento registra quién lo emitió.
- [ ] Las contraseñas son de 10 caracteres o más.
- [ ] `http.verificar_tls` sigue en `true`.

Las llaves de firma son, para efectos prácticos, su firma. Quien las tenga puede
emitir facturas a su nombre.

---

## G. Conciliación mensual

Antes de declarar el IVA:

1. Entre al **Panel**: muestra documentos certificados, base gravable, IVA y
   total del mes en curso.
2. Contraste esos totales con lo que SAT tiene registrado en la Agencia Virtual.
3. Si no cuadran, revise en **Documentos** filtrando por estado:
   - `PENDIENTE` → ventas que nunca llegaron a certificarse.
   - `RECHAZADO` → ventas que quedaron sin documento válido.
   - `ANULADO` → no deben sumar al débito fiscal.

**El dato oficial es siempre el que SAT tiene registrado**, no el de este
sistema. Los totales del panel son para conciliar, no para declarar.

---

## Lo que este sistema NO hace

Dicho claro, para que no haya sorpresas:

- No lo habilita como emisor FEL ante SAT.
- No se conecta directo a SAT: siempre pasa por su certificador.
- No decide su régimen tributario ni qué frases le aplican.
- No lleva su contabilidad ni genera su declaración de IVA.
- No valida el XML contra los XSD oficiales de SAT en su servidor — esa
  validación la hace el certificador, que es quien tiene la versión vigente del
  esquema. Por eso conviene probar en su ambiente de pruebas antes de producción.
- No envía el DTE por correo al cliente automáticamente (el correo del receptor
  viaja en el XML; la mayoría de certificadores lo envían ellos — confírmelo).
