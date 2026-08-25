# 01 — Cómo funciona FEL en Guatemala

## El circuito completo

FEL (Factura Electrónica en Línea) es el régimen de facturación electrónica de
la SAT de Guatemala. El punto que hay que entender antes que nada:

```
  SU SISTEMA                CERTIFICADOR                    SAT
  ─────────                 ────────────                    ───
  1. Arma el XML  ────────► 2. Firma con la llave
     del DTE                   del emisor
                           3. Valida contra el
                              esquema y las reglas
                           4. CERTIFICA: asigna
                              serie, número y el
                              número de autorización ─────► 5. Registra el DTE
                              (UUID)
  7. Guarda e imprime ◄──── 6. Devuelve el XML
     el DTE certificado        certificado
```

**Usted no se conecta directo a SAT.** No existe una API pública de SAT para
que un contribuyente certifique sus propias facturas. La certificación la hace
un tercero autorizado: el **Certificador de Documentos Tributarios
Electrónicos**.

Cuando usted factura hoy desde el portal gratuito de SAT, ese portal está
haciendo internamente lo mismo: SAT actúa como certificador de sus propios
documentos. Al pasar a su propio sistema, usted elige y contrata a un
certificador comercial.

## Qué aporta cada parte

| Parte | Responsabilidad |
|---|---|
| **Usted (este sistema)** | Capturar la venta, calcular IVA, armar el XML conforme al esquema, conservar el DTE, imprimir la representación gráfica |
| **Certificador** | Firmar electrónicamente, validar, certificar (asignar el UUID), transmitir a SAT, dar disponibilidad del servicio |
| **SAT** | Recibir, registrar y publicar el DTE en su verificador; fiscalizar |

## El documento: qué lleva el XML

El DTE es un XML con el espacio de nombres
`http://www.sat.gob.gt/dte/fel/0.2.0`. Su estructura, simplificada:

```xml
<dte:GTDocumento Version="0.1">
  <dte:SAT ClaseDocumento="dte">
    <dte:DTE ID="DatosCertificados">
      <dte:DatosEmision ID="DatosEmision">
        <dte:DatosGenerales Tipo="FACT" FechaHoraEmision="..." CodigoMoneda="GTQ"/>
        <dte:Emisor .../>       <!-- NIT, nombre, afiliación IVA, establecimiento -->
        <dte:Receptor .../>     <!-- NIT o CF, nombre, correo -->
        <dte:Frases/>           <!-- las frases obligatorias de su régimen -->
        <dte:Items/>            <!-- detalle con IVA desglosado por línea -->
        <dte:Totales/>          <!-- total de impuestos y gran total -->
        <dte:Complementos/>     <!-- notas, cambiaria, exportación -->
      </dte:DatosEmision>
    </dte:DTE>
    <dte:Adenda/>               <!-- datos NO fiscales, libres -->
  </dte:SAT>
</dte:GTDocumento>
```

Al certificarse, el certificador le agrega dentro de `dte:DTE` un nodo
`dte:Certificacion` con el NIT y nombre del certificador, la fecha/hora de
certificación y el **número de autorización** con su serie y número. Ese XML
certificado es el documento fiscal; el que usted armó, por sí solo, no lo es.

## El IVA se desglosa hacia adentro

En Guatemala el precio que se le cobra al cliente **ya incluye el IVA del 12 %**.
Por eso el desglose va al revés de lo que mucha gente espera:

```
Total de la línea      = cantidad × precio unitario − descuento
Monto gravable (base)  = Total / 1.12
Monto del impuesto     = Total − Monto gravable
```

Ejemplo con Q1,120.00 de venta:

| Concepto | Monto |
|---|---:|
| Base gravable | Q1,000.00 |
| IVA 12 % | Q120.00 |
| **Gran total** | **Q1,120.00** |

El `GranTotal` del XML siempre es la suma de los `Total` de cada línea, nunca
se recalcula desde la base: así no aparecen diferencias de centavos.

**Regímenes sin crédito fiscal** (pequeño contribuyente `FPEQ`/`FAPE`,
agropecuario `FACA`/`FAAE`): no desglosan IVA. El sistema reporta
`MontoGravable = Total` y `MontoImpuesto = 0.00`, y agrega la frase
"No genera derecho a crédito fiscal".

## Las frases

Cada DTE debe llevar al menos una **frase**, identificada por un
`TipoFrase` y un `CodigoEscenario`. Indican bajo qué régimen se emite: si está
sujeto a pagos trimestrales de ISR, si es agente de retención de IVA, si es
pequeño contribuyente, etc.

El sistema propone las frases según la afiliación de IVA que usted configure,
pero **la combinación correcta depende de su situación tributaria concreta**.
Confírmela con su contador y contra el catálogo vigente del anexo técnico de
SAT. Puede ajustar el catálogo en `src/Dte/Catalogos.php` sin tocar nada más.

## Anular vs. nota de crédito

Son cosas distintas y se confunden seguido:

- **Anulación**: deja el DTE sin efecto. Solo se puede dentro del plazo que
  permite la normativa (habitualmente el mismo período de declaración). Se
  transmite a SAT y queda registrada.
- **Nota de crédito (`NCRE`)**: ajusta un documento ya declarado. Es lo correcto
  cuando la anulación ya no procede por plazo, o cuando hay una devolución
  parcial. Lleva obligatoriamente la referencia al documento origen.

El sistema le avisa cuando una anulación se sale del plazo que usted configure,
pero **no la bloquea**: la palabra final la tiene SAT a través del certificador.

## Contingencia

Si se cae el internet o el servicio del certificador, el documento **no se
pierde**: queda guardado en estado `PENDIENTE` con su XML ya armado y su
identificador interno. El cron lo reintenta después usando exactamente el mismo
XML e identificador, para que el certificador lo trate como el mismo documento
y no se dupliquen folios.

Importante distinguir dos fallas:

- **Falla de comunicación** → `PENDIENTE`, se reintenta.
- **Rechazo de contenido** (un NIT que no existe, una frase inválida) →
  `RECHAZADO`, **no se reintenta**. Hay que corregir y emitir uno nuevo;
  reenviar el mismo XML solo repetiría el error.
