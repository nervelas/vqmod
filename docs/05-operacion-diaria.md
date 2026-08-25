# 05 — Operación diaria

Manual para quien factura todos los días.

## Emitir una factura

**Menú → Nuevo documento**

1. **Tipo de DTE**: `FACT` para la factura normal. La lista cambia el
   comportamiento del cálculo: los tipos de pequeño contribuyente y
   agropecuario no desglosan IVA y el sistema se lo avisa en pantalla.

2. **Receptor**: elija un cliente guardado (rellena todo solo) o capture a mano.
   - Consumidor final: deje `CF`.
   - Con NIT: escríbalo con o sin guion, da igual. Si el dígito verificador está
     mal, el sistema no deja emitir.
   - Con DPI: cambie *Tipo de identificación* a **CUI/DPI**.
   - Extranjero: **Extranjero / pasaporte**.

3. **Detalle**: una línea por producto o servicio.
   - Si eligió un producto del catálogo, se llenan descripción, precio, unidad
     y tipo automáticamente.
   - **El precio se captura con IVA incluido**, tal como se lo cobra al cliente.
     El sistema desglosa el impuesto solo.
   - El descuento va en quetzales, no en porcentaje.
   - *+ Agregar línea* para más renglones; la **×** quita una.

4. **Frases**: vienen marcadas las de su régimen. No las cambie sin consultar
   con su contador.

5. **Certificar y emitir**.

Si todo está bien, en segundos aparece el documento con su **número de
autorización**. Desde ahí puede imprimirlo, guardarlo como PDF o descargar el XML.

## Entregar el documento al cliente

Desde el detalle del documento:

- **Representación gráfica** → la abre en el navegador.
- **Imprimir / PDF** → abre el diálogo de impresión. En *Destino* elija
  "Guardar como PDF" para mandarlo por correo o WhatsApp.
- **Descargar XML** → el XML certificado. Algunos clientes (sobre todo empresas
  grandes) lo piden para su contabilidad.

El correo del receptor viaja dentro del XML. La mayoría de certificadores envían
el DTE por correo automáticamente — confirme con el suyo si lo hace, para no
duplicar el envío.

## Anular un documento

**Documentos → abrir el documento → Anular**

Escriba un motivo real ("cliente canceló el pedido", "error en el NIT del
receptor"). No ponga solo "error": ese texto queda registrado en SAT.

La anulación se transmite y el documento queda marcado como `ANULADO`. En la
impresión sale la marca de agua.

**Si el sistema le avisa que pasó el plazo**, o si el certificador la rechaza
por antigüedad: no insista. Emita una **nota de crédito**.

## Nota de crédito

Es lo correcto cuando el documento ya no se puede anular, o cuando la devolución
es parcial.

1. **Nuevo documento** → tipo `NCRE`.
2. Mismo receptor que la factura original.
3. En el detalle, lo que se está devolviendo o ajustando.
4. En **Referencia al documento origen** (obligatoria):
   - Número de autorización de la factura original (el UUID).
   - Fecha de emisión original.
   - Serie y número originales.
   - Motivo del ajuste.

Todos esos datos los encuentra abriendo la factura original en **Documentos**.

## Cuando se cae el internet

**No pasa nada: siga facturando.**

El documento se guarda con estado `PENDIENTE` y su XML ya armado. Cuando vuelva
la conexión, el cron lo certifica solo, sin duplicar folios.

En el **Panel** aparece la sección *Documentos en contingencia* con los que
están esperando. Si quiere apurar uno, tiene el botón **Reintentar**.

Advertencia importante: mientras un documento está `PENDIENTE`, **todavía no
tiene número de autorización**. Si necesita entregarle algo al cliente en ese
momento, entrégueselo cuando se certifique, o avísele que se lo manda después.

Si un documento aparece como `RECHAZADO`, no lo reintente: léa el mensaje de
error en su detalle, corrija y emita uno nuevo.

## Clientes y productos

**Clientes** — guarde los que factura seguido. Al emitir, los elige de la lista
y se llena todo solo, sin errores de tecleo en el NIT.

**Productos** — su catálogo con precio (con IVA incluido), unidad de medida y si
es bien o servicio. Marque *Exento de IVA* solo si realmente lo está;
consúltelo con su contador.

Los dos se pueden desactivar en lugar de borrar, para no perder el historial de
los documentos que ya los usaron.

## Cierre de mes

1. **Panel** → totales del mes: documentos certificados, base gravable, IVA y
   total facturado.
2. **Documentos** → filtre por estado `PENDIENTE` y `RECHAZADO`. Antes de
   declarar, esas dos listas deberían estar vacías.
3. Contraste los totales con lo que SAT tiene en la Agencia Virtual.
4. Entregue a su contador los XML del mes: están en
   `storage/xml/AAAA/MM/`, o los descarga uno por uno desde el listado.

## Respaldos

Esto no es opcional. Los DTE hay que conservarlos durante el plazo de
prescripción.

- **Base de datos**: cPanel → *Copias de seguridad* → descargue el respaldo de
  la base al menos una vez al mes, y guárdelo fuera del servidor.
- **XML en archivo**: comprima y descargue `storage/xml/` con la misma
  frecuencia.
- **Pruebe restaurar** un respaldo al menos una vez al año. Un respaldo que
  nunca se probó no sirve de nada.

## Usuarios

Cada persona que facture debe tener su propio usuario: el sistema registra quién
emitió cada documento.

```bash
php bin/usuario.php crear vendedor1 "Juan Pérez" unaClaveLarga123 operador
php bin/usuario.php clave vendedor1 otraClaveLarga456
php bin/usuario.php baja  vendedor1
php bin/usuario.php listar
```

## Cuando algo falla

1. Abra el documento → sección **Bitácora con el certificador**. Ahí está el
   mensaje exacto que devolvió el certificador.
2. Si el mensaje habla de datos del emisor, revise que coincidan con su RTU.
3. Si habla de frases o de un código de catálogo, es tema de configuración:
   consulte con su contador y revise `src/Dte/Catalogos.php`.
4. Si es un error de conexión o un código interno del certificador, mándele ese
   texto tal cual a su soporte técnico.
5. El detalle técnico completo está en `storage/logs/fel-AAAA-MM.log`.

## Estados de un documento

| Estado | Significa |
|---|---|
| `PENDIENTE` | Guardado, esperando certificarse. **Todavía no es válido.** |
| `CERTIFICADO` | Tiene número de autorización de SAT. **Es válido.** |
| `RECHAZADO` | El certificador rechazó el contenido. Corregir y emitir uno nuevo. |
| `ANULADO` | Se certificó y después se anuló ante SAT. |
| `BORRADOR` | Guardado sin enviar. |
