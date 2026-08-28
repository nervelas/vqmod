# 06 — Vender el servicio

Guía práctica para ofrecer facturación electrónica a sus clientes usando esta
plataforma. Está escrita para quien ya vende sitios web y tiendas en línea y
quiere agregar esta herramienta a su catálogo.

---

## Qué está vendiendo exactamente

**No** está vendiendo la certificación ante SAT: eso lo da el certificador
(INFILE y compañía), y cada cliente lo contrata.

Usted vende **el sistema con el que su cliente factura**: la pantalla donde
captura la venta, su catálogo de productos y clientes, la factura impresa con
su logo, el control de lo emitido, los respaldos y el soporte.

Vale la pena tenerlo claro porque es lo que va a explicar en cada visita:

> "La SAT le da el portal gratis, pero ahí usted teclea todo a mano, factura
> por factura, sin su catálogo de productos, sin su logo y sin un lugar donde
> quede su historial. Yo le pongo su propio sistema: entra con su usuario,
> elige el producto, y la factura sale certificada en segundos con su marca."

---

## Los tres modelos de cobro

Elija uno o combine. Los tres funcionan sobre la misma instalación.

### 1. Mensualidad por empresa (el que recomiendo)

Usted mantiene la instalación y cobra una renta mensual a cada cliente.

| Concepto | Referencia |
|---|---|
| Instalación y puesta en marcha | Un pago inicial, incluye capacitación |
| Renta mensual | Por empresa, con un tope de documentos |
| Documentos adicionales | Por encima del tope |
| Capacitación extra o personalización | Aparte |

La pantalla **Empresas** le muestra el consumo del mes de cada cliente
(documentos certificados y monto facturado), que es exactamente lo que necesita
para emitirle su factura.

**Ventaja:** ingreso recurrente y una sola instalación que mantener.
**Cuidado:** el certificador lo contrata el cliente, a su nombre. No se meta a
revender folios de certificación salvo que tenga un acuerdo formal con el
certificador — el contrato y la responsabilidad fiscal son del contribuyente.

### 2. Instalación propia por cliente

Instala el sistema en el hosting del cliente y cobra instalación + soporte.

**Ventaja:** el cliente siente que "es suyo", y usted no carga con el
alojamiento ni con la disponibilidad.
**Cuidado:** cada instalación es una que actualizar. Con más de cinco o seis se
vuelve pesado.

### 3. Como complemento de la tienda en línea

Para clientes a los que ya les hizo tienda: la factura sale del mismo lugar
donde entra el pedido. Aquí el valor es la integración, y se cobra como
proyecto.

---

## Cómo dar de alta a un cliente

Toma unos 15 minutos, una vez que el cliente le pasó sus datos.

### Antes de empezar, pídale al cliente

1. **Constancia del RTU** (para copiar razón social y NIT tal cual).
2. **Código de establecimiento** que le asignó SAT.
3. Confirmación de que está **habilitado como emisor FEL** en la Agencia Virtual.
4. **Credenciales del certificador**: usuario y llave de API, llave y alias de
   firma, y las URL de su manual técnico.
5. Logo en PNG y el color de su marca.
6. Con qué **régimen de IVA** está inscrito (que lo confirme su contador).

> Si el cliente todavía no tiene certificador, ayúdelo a contratarlo. Es el
> paso que más se atora y donde usted aporta más valor.

### En el sistema

1. Entre como **administrador de la plataforma**.
2. **Empresas → Agregar empresa**.
3. Llene los datos del emisor **exactamente** como aparecen en el RTU. Una coma
   o una tilde de diferencia hace que el certificador rechace los documentos.
4. En **Certificador**, elija el proveedor y pegue las credenciales. Se guardan
   cifradas.
5. Deje **Ambiente: pruebas** por ahora.
6. Elija el **formato de impresión**: hoja carta para oficinas y servicios,
   ticket 80 mm para tiendas y restaurantes con impresora térmica.
7. Ponga el color de marca y el logo.
8. Cree el **primer usuario** de la empresa en el mismo formulario.

### Probar antes de cobrar

1. **Entrar** a la empresa desde el listado.
2. Emitir dos o tres documentos de prueba: una factura normal, una a
   consumidor final y una nota de crédito.
3. Revisar la impresión en los dos formatos y que el QR se lea con el celular.
4. Cuando el certificador confirme que las credenciales de producción están
   activas, cambie **Ambiente: producción** y emita **una** factura real de
   monto bajo.
5. Verifíquela en el portal de SAT. Si aparece, ya está listo.

Mientras la empresa esté en pruebas o con el certificador simulado, el sistema
muestra una cinta amarilla en todas las pantallas y marca los documentos como
sin validez fiscal. Nadie va a confundirse.

---

## La demostración que cierra la venta

Tenga **una empresa de demostración** permanente con el certificador
`simulador`. Ahí puede emitir todo lo que quiera sin gastar folios ni tocar
la red.

Guion de 5 minutos:

1. **Entre con el usuario de la demo.** "Así entra su cajera."
2. **Nuevo documento.** Elija un producto del catálogo: se llenan solos la
   descripción, el precio y la unidad. "No teclea nada."
3. **Escriba un NIT mal a propósito.** El sistema lo rechaza antes de enviarlo.
   "Esto le evita facturas rechazadas y anulaciones."
4. **Certifique.** Aparece el número de autorización en segundos.
5. **Imprima el ticket** y **escanee el QR con el celular** del cliente.
   Este es el momento que vende.
6. **Muestre el listado** con filtros y el resumen del mes. "Esto es lo que le
   pasa a su contador."

Cierre con la contingencia, que es el argumento que nadie más le va a dar:

> "Si se le cae el internet, usted sigue facturando. El sistema guarda el
> documento y lo certifica solo cuando vuelve la señal, sin duplicar números.
> En el portal de SAT, sin internet, simplemente no factura."

---

## Objeciones que le van a poner

**"El portal de la SAT es gratis."**
Y sigue estando disponible. Lo que se paga aquí es no teclear cada factura a
mano, tener catálogo, historial, respaldo, marca propia y poder facturar aunque
se caiga el internet. Ponga números: si facturan 200 documentos al mes y cada
uno toma tres minutos en el portal, son diez horas mensuales.

**"¿Y si la SAT no lo acepta?"**
La factura la certifica un certificador autorizado por SAT: es exactamente el
mismo documento y el mismo número de autorización que emite el portal.
Muéstrele una factura de prueba verificada en el portal de SAT.

**"Ya tengo contador, él me factura."**
Perfecto: el contador sigue haciendo la declaración. Esto es para que la
factura salga en el mostrador, en el momento de la venta.

**"¿Y si usted desaparece?"**
Respuesta honesta y por escrito en el contrato: los XML son del cliente y se le
entregan cuando los pida. Ofrézcale un respaldo mensual de sus XML por correo.
Esa promesa cumplida vale más que cualquier descuento.

---

## Lo que debe dejar por escrito

Un contrato de una página evita el 90 % de los problemas:

- **Qué incluye el servicio**: el sistema, el alojamiento, los respaldos, el
  soporte en horario hábil.
- **Qué NO incluye**: el contrato con el certificador, el trámite de
  habilitación ante SAT, la asesoría tributaria y la declaración de impuestos.
- **De quién son los datos**: del cliente. Cómo se los entrega si se va.
- **Disponibilidad**: qué pasa si se cae el hosting o el certificador, y en
  cuánto tiempo responde usted.
- **Precio**, qué incluye la mensualidad y cómo se cobra lo adicional.

Sea explícito en una frase: *"La responsabilidad tributaria es del
contribuyente. El proveedor entrega la herramienta, no asesoría fiscal."*

---

## Su operación del día a día

Poco trabajo, pero no cero:

| Cada cuándo | Qué |
|---|---|
| Diario | Ver el panel de contingencia. Si hay pendientes acumulados, algo pasa con un certificador. |
| Semanal | Revisar `storage/logs/` por errores repetidos. |
| Mensual | Sacar el uso por empresa desde **Empresas** y facturar el servicio. |
| Mensual | Descargar el respaldo de la base y de `storage/xml/`, y guardarlo fuera del servidor. |
| Trimestral | Probar restaurar un respaldo. Un respaldo que nunca se restauró no es un respaldo. |
| Cuando SAT cambie algo | Revisar catálogos y frases, y avisar a sus clientes. |

**Lo más importante:** el cron de contingencia
(`bin/reintentar_pendientes.php`) tiene que estar corriendo. Es lo que rescata
los documentos que no se pudieron certificar. Si no corre, nadie los reintenta.

---

## Dónde crecer después

Cuando ya tenga clientes usando el sistema, esto es lo que más le van a pedir,
en orden:

1. **Envío automático del DTE por correo al cliente final.**
   (Muchos certificadores ya lo hacen; confirme antes de construirlo.)
2. **Reporte de ventas por vendedor o por producto.**
3. **Integración con su tienda en línea**: que el pedido genere la factura.
4. **App o vista para el celular** en el mostrador.
5. **Portal para el contador**, con acceso de solo lectura a los XML del mes.

Cóbrelos como desarrollo aparte. Ese es el margen real del negocio.
