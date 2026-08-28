<?php
declare(strict_types=1);

namespace Fel\Presentacion;

use Fel\Core\Config;
use Fel\Dte\Calculator;
use Fel\Dte\Catalogos;
use Fel\Plataforma\Empresa;

/**
 * Representacion grafica del DTE: lo que se entrega al cliente.
 *
 * Dos formatos:
 *   - carta:  hoja completa, para enviar por correo o guardar como PDF.
 *   - ticket: rollo de 80 mm, para impresora termica de punto de venta.
 *
 * SAT no obliga a un diseño concreto, pero SI a que aparezcan:
 *   - Nombre y NIT del emisor, nombre comercial y direccion del establecimiento
 *   - Tipo de documento
 *   - Numero de autorizacion (UUID), serie y numero
 *   - Fecha y hora de emision y de certificacion
 *   - Identificacion y nombre del receptor
 *   - Detalle, moneda y gran total
 *   - Las frases obligatorias del regimen
 *   - Nombre y NIT del certificador
 *
 * Ambos formatos incluyen el codigo QR de consulta, generado sin librerias
 * externas (ver CodigoQr).
 */
final class RepresentacionGrafica
{
    /**
     * @param array<string,mixed> $documento Fila de fel_documentos
     * @param list<array<string,mixed>> $items Filas de fel_documento_items
     * @param list<string> $frases Textos de las frases aplicadas
     * @param string|null $formato 'carta' o 'ticket'. Si es null usa el de la empresa.
     */
    public function html(
        Empresa $empresa,
        array $documento,
        array $items,
        array $frases = [],
        ?string $formato = null,
    ): string {
        $formato = $formato ?? $empresa->formatoImpresion();
        $formato = $formato === 'ticket' ? 'ticket' : 'carta';

        return $formato === 'ticket'
            ? $this->ticket($empresa, $documento, $items, $frases)
            : $this->carta($empresa, $documento, $items, $frases);
    }

    /**
     * Contenido que se codifica en el QR.
     *
     * Cada certificador publica su propia URL de consulta. Se deja como
     * plantilla configurable (sat.plantilla_qr) para poder usar la del
     * certificador contratado sin tocar codigo.
     *
     * @param array<string,mixed> $documento
     */
    public function contenidoQr(array $documento): string
    {
        $plantilla = (string) Config::get(
            'sat.plantilla_qr',
            'https://felpub.c.sat.gob.gt/verificador-web/publico/vistas/verificacionDte.jsf'
            . '?tipo=autorizacion&numero={UUID}&emisor={NIT_EMISOR}&receptor={NIT_RECEPTOR}&monto={MONTO}'
        );

        return strtr($plantilla, [
            '{UUID}'          => (string) $documento['uuid'],
            '{NIT_EMISOR}'    => (string) $documento['emisor_nit'],
            '{NIT_RECEPTOR}'  => (string) $documento['receptor_id'],
            '{MONTO}'         => number_format((float) $documento['gran_total'], 2, '.', ''),
            '{FECHA}'         => substr((string) $documento['fecha_emision'], 0, 10),
            '{SERIE}'         => (string) $documento['serie'],
            '{NUMERO}'        => (string) $documento['numero'],
        ]);
    }

    /** @param array<string,mixed> $documento */
    private function qrSvg(array $documento, int $lado): string
    {
        if (trim((string) $documento['uuid']) === '') {
            return '';
        }

        try {
            return (new CodigoQr($this->contenidoQr($documento)))->svg($lado, 2);
        } catch (\Throwable) {
            // Un QR que no se puede generar no debe impedir imprimir la factura.
            return '';
        }
    }

    // ------------------------------------------------------------------ carta

    /**
     * @param array<string,mixed> $documento
     * @param list<array<string,mixed>> $items
     * @param list<string> $frases
     */
    private function carta(Empresa $empresa, array $documento, array $items, array $frases): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $n = static fn (mixed $v): string => number_format((float) $v, 2, '.', ',');

        $tipos      = Catalogos::tiposDte();
        $nombreTipo = $tipos[(string) $documento['tipo']]['nombre'] ?? (string) $documento['tipo'];
        $anulado    = $documento['estado'] === 'ANULADO';
        $certificado = $documento['estado'] === 'CERTIFICADO' || $anulado;

        $moneda  = (string) $documento['moneda'];
        $simbolo = $this->simbolo($moneda);
        $color   = $empresa->colorMarca();

        $filas = '';
        foreach ($items as $item) {
            $filas .= sprintf(
                '<tr><td class="c">%s</td><td class="c">%s</td><td class="c">%s</td><td>%s</td>'
                . '<td class="d">%s</td><td class="d">%s</td><td class="d">%s</td></tr>',
                $e($item['numero_linea']),
                $e($this->cantidad($item['cantidad'])),
                $e($item['unidad_medida']),
                $e($item['descripcion']),
                $n($item['precio_unitario']),
                $n($item['descuento']),
                $n($item['total'])
            );
        }

        $listaFrases = '';
        foreach ($frases as $frase) {
            $listaFrases .= '<li>' . $e($frase) . '</li>';
        }

        $logo = $empresa->logo() !== ''
            ? '<img class="logo" src="' . $e($empresa->logo()) . '" alt="">'
            : '';

        $qr = $this->qrSvg($documento, 110);
        $bloqueQr = $qr === '' ? '' : sprintf(
            '<div class="qr">%s<span>Escanee para verificar<br>este documento ante SAT</span></div>',
            $qr
        );

        $enLetras = Calculator::montoEnLetras((float) $documento['gran_total'], $moneda);

        $marcaAnulado = $anulado ? '<div class="anulado">ANULADO</div>' : '';
        $avisoPrueba  = $empresa->esSimulador()
            ? '<div class="aviso">DOCUMENTO DE PRUEBA — certificador simulado. SIN VALIDEZ FISCAL.</div>'
            : '';
        $avisoEstado = $certificado
            ? ''
            : '<div class="aviso">DOCUMENTO NO CERTIFICADO — estado: ' . $e($documento['estado'])
              . '. Esta impresión NO tiene validez fiscal.</div>';

        $css = $this->estilosCarta($color);

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$e($nombreTipo)} {$e($documento['serie'])}-{$e($documento['numero'])}</title>
<style>{$css}</style>
</head>
<body>
{$marcaAnulado}
<div class="hoja">
  {$avisoPrueba}
  {$avisoEstado}

  <header class="encabezado">
    <div class="emisor">
      {$logo}
      <h1>{$e($empresa->nombreComercial())}</h1>
      <p class="razon">{$e($documento['emisor_nombre'])}</p>
      <p>NIT: <strong>{$e($documento['emisor_nit'])}</strong></p>
      <p>{$e($empresa->valor('direccion'))}</p>
      <p>{$e($empresa->valor('municipio'))}, {$e($empresa->valor('departamento'))}, {$e($empresa->valor('pais'))}</p>
      <p>{$e($empresa->valor('correo'))} {$e($empresa->valor('telefono'))}</p>
      <p>Establecimiento: {$e($documento['establecimiento'])}</p>
    </div>
    <div class="documento">
      <p class="fel">FEL — DOCUMENTO TRIBUTARIO ELECTRÓNICO</p>
      <h2>{$e(mb_strtoupper($nombreTipo))}</h2>
      <table class="meta">
        <tr><th>Serie</th><td>{$e($documento['serie'] ?: '—')}</td></tr>
        <tr><th>Número</th><td>{$e($documento['numero'] ?: '—')}</td></tr>
        <tr><th>Emisión</th><td>{$e($this->fecha((string) $documento['fecha_emision']))}</td></tr>
        <tr><th>Certificación</th><td>{$e($this->fecha((string) $documento['fecha_certificacion']))}</td></tr>
        <tr><th>Moneda</th><td>{$e($moneda)}</td></tr>
      </table>
      <div class="autorizacion">
        <span>Número de autorización</span>
        <code>{$e($documento['uuid'] ?: '—')}</code>
      </div>
    </div>
  </header>

  <section class="receptor">
    <table>
      <tr>
        <th>NIT / Identificación</th><td>{$e($documento['receptor_id'])}</td>
        <th>Nombre</th><td>{$e($documento['receptor_nombre'])}</td>
      </tr>
      <tr><th>Correo</th><td colspan="3">{$e($documento['receptor_correo'] ?: '—')}</td></tr>
    </table>
  </section>

  <table class="detalle">
    <thead>
      <tr><th>#</th><th>Cant.</th><th>U/M</th><th>Descripción</th>
          <th class="d">P. unitario</th><th class="d">Descuento</th><th class="d">Total</th></tr>
    </thead>
    <tbody>{$filas}</tbody>
  </table>

  <section class="totales">
    <div class="letras">
      <span>Total en letras</span>
      <p>{$e($enLetras)}</p>
    </div>
    <table>
      <tr><th>Subtotal gravable</th><td>{$simbolo}{$n($documento['total_gravable'])}</td></tr>
      <tr><th>Descuentos</th><td>{$simbolo}{$n($documento['total_descuentos'])}</td></tr>
      <tr><th>IVA</th><td>{$simbolo}{$n($documento['total_iva'])}</td></tr>
      <tr class="grantotal"><th>Total</th><td>{$simbolo}{$n($documento['gran_total'])}</td></tr>
    </table>
  </section>

  <section class="frases"><ul>{$listaFrases}</ul></section>

  <footer>
    {$bloqueQr}
    <div class="pie">
      <p><strong>Certificador:</strong> {$e($empresa->certificadorNombre() ?: $documento['certificador'])}
         {$e($empresa->certificadorNit() !== '' ? '— NIT ' . $empresa->certificadorNit() : '')}</p>
      <p>Verifique este documento en el portal de SAT con el número de autorización.</p>
      <p class="obs">{$e($documento['observaciones'])}</p>
    </div>
  </footer>
</div>
<script>if (location.search.indexOf('imprimir=1') !== -1) { window.print(); }</script>
</body>
</html>
HTML;
    }

    // ----------------------------------------------------------------- ticket

    /**
     * @param array<string,mixed> $documento
     * @param list<array<string,mixed>> $items
     * @param list<string> $frases
     */
    private function ticket(Empresa $empresa, array $documento, array $items, array $frases): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $n = static fn (mixed $v): string => number_format((float) $v, 2, '.', ',');

        $tipos      = Catalogos::tiposDte();
        $nombreTipo = $tipos[(string) $documento['tipo']]['nombre'] ?? (string) $documento['tipo'];
        $moneda     = (string) $documento['moneda'];
        $simbolo    = $this->simbolo($moneda);
        $anulado    = $documento['estado'] === 'ANULADO';

        $filas = '';
        foreach ($items as $item) {
            $filas .= sprintf(
                '<tr><td class="c">%s</td><td class="desc">%s</td><td class="d">%s</td><td class="d">%s</td></tr>',
                $e($this->cantidad($item['cantidad'])),
                $e($item['descripcion']),
                $n($item['precio_unitario']),
                $n($item['total'])
            );
        }

        $listaFrases = '';
        foreach ($frases as $frase) {
            $listaFrases .= '<p class="frase">' . $e(mb_strtoupper($frase)) . '</p>';
        }

        $logo = $empresa->logo() !== ''
            ? '<img class="logo" src="' . $e($empresa->logo()) . '" alt="">'
            : '';

        $qr = $this->qrSvg($documento, 130);
        $bloqueQr = $qr === ''
            ? ''
            : '<div class="qr">' . $qr . '<p class="mini">ESCANEA EL CÓDIGO DESDE TU CELULAR</p></div>';

        $avisoPrueba = $empresa->esSimulador()
            ? '<p class="alerta">** DOCUMENTO DE PRUEBA — SIN VALIDEZ FISCAL **</p>'
            : '';
        $avisoAnulado = $anulado ? '<p class="alerta">** DOCUMENTO ANULADO **</p>' : '';
        $avisoEstado  = in_array($documento['estado'], ['CERTIFICADO', 'ANULADO'], true)
            ? ''
            : '<p class="alerta">** NO CERTIFICADO (' . $e($documento['estado']) . ') — SIN VALIDEZ FISCAL **</p>';

        $enLetras = Calculator::montoEnLetras((float) $documento['gran_total'], $moneda);
        $css      = $this->estilosTicket();

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$e($nombreTipo)} {$e($documento['serie'])}-{$e($documento['numero'])}</title>
<style>{$css}</style>
</head>
<body>
<div class="rollo">
  {$avisoPrueba}{$avisoAnulado}{$avisoEstado}

  <div class="centro">
    {$logo}
    <p class="fuerte">{$e($empresa->nombreComercial())}</p>
    <p>{$e($documento['emisor_nombre'])}</p>
    <p>{$e($empresa->valor('direccion'))}</p>
    <p>{$e($empresa->valor('municipio'))}, {$e($empresa->valor('departamento'))}</p>
    <p>NIT: {$e($documento['emisor_nit'])}</p>
  </div>

  <hr>

  <p class="centro fuerte">FEL - DOCUMENTO TRIBUTARIO ELECTRÓNICO</p>
  <p class="centro fuerte">{$e(mb_strtoupper($nombreTipo))}</p>

  <p><span class="rot">SERIE:</span> {$e($documento['serie'] ?: '—')}</p>
  <p><span class="rot">No.:</span> {$e($documento['numero'] ?: '—')}</p>
  <p><span class="rot">FECHA:</span> {$e($this->fecha((string) $documento['fecha_emision']))}</p>
  <p><span class="rot">Cliente Nit:</span> {$e($documento['receptor_id'])}</p>
  <p><span class="rot">Nombre:</span> {$e($documento['receptor_nombre'])}</p>
  <p><span class="rot">Moneda:</span> {$e($moneda)}</p>

  <hr>
  <p class="centro fuerte">DETALLE</p>

  <table class="detalle">
    <thead><tr><th>Cant.</th><th>Descripción</th><th class="d">Precio U.</th><th class="d">Total</th></tr></thead>
    <tbody>{$filas}</tbody>
  </table>

  <hr>

  <table class="totales">
    <tr><td>Gravable</td><td class="d">{$simbolo}{$n($documento['total_gravable'])}</td></tr>
    <tr><td>Descuento</td><td class="d">{$simbolo}{$n($documento['total_descuentos'])}</td></tr>
    <tr><td>IVA</td><td class="d">{$simbolo}{$n($documento['total_iva'])}</td></tr>
    <tr class="grande"><td>TOTAL</td><td class="d">{$simbolo}{$n($documento['gran_total'])}</td></tr>
  </table>

  <p class="letras">TOTAL EN LETRAS:<br>{$e($enLetras)}</p>

  {$listaFrases}

  <hr>
  <p class="centro fuerte">Datos Del Certificador</p>
  <p>CERTIFICADOR: {$e($empresa->certificadorNombre() ?: $documento['certificador'])}</p>
  <p>NIT: {$e($empresa->certificadorNit() ?: '—')}</p>
  <p>Fecha y hora de certificación:<br>{$e($this->fecha((string) $documento['fecha_certificacion']))}</p>
  <p class="uuid">UUID: {$e($documento['uuid'] ?: '—')}</p>

  {$bloqueQr}

  <p class="centro mini">{$e($documento['observaciones'])}</p>
</div>
<script>if (location.search.indexOf('imprimir=1') !== -1) { window.print(); }</script>
</body>
</html>
HTML;
    }

    // ------------------------------------------------------------- auxiliares

    private function simbolo(string $moneda): string
    {
        return match ($moneda) {
            'GTQ'   => 'Q',
            'USD'   => '$',
            default => $moneda . ' ',
        };
    }

    private function cantidad(mixed $valor): string
    {
        $texto = number_format((float) $valor, 6, '.', '');

        return rtrim(rtrim($texto, '0'), '.') ?: '0';
    }

    private function fecha(string $iso): string
    {
        if (trim($iso) === '') {
            return '—';
        }

        try {
            return (new \DateTimeImmutable($iso))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $iso;
        }
    }

    private function estilosCarta(string $color): string
    {
        return <<<CSS
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#eef1f5;font:13px/1.45 "Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#16202c}
.hoja{max-width:840px;margin:0 auto;background:#fff;padding:28px 32px;border-radius:6px;box-shadow:0 2px 14px rgba(0,0,0,.09);position:relative}
h1{font-size:19px;margin:0 0 2px;color:{$color}}
h2{font-size:17px;margin:0;letter-spacing:.06em}
p{margin:1px 0}
.logo{max-height:56px;max-width:210px;margin-bottom:8px;display:block}
.encabezado{display:flex;gap:24px;justify-content:space-between;border-bottom:2px solid {$color};padding-bottom:14px}
.emisor{flex:1 1 55%;font-size:12px}
.emisor .razon{font-weight:600}
.documento{flex:1 1 45%;text-align:right}
.fel{font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#5b6875;margin-bottom:4px}
table{border-collapse:collapse;width:100%}
.meta{margin-top:8px}
.meta th{text-align:right;padding:1px 8px 1px 0;font-weight:600;color:#5b6875;font-size:11px}
.meta td{text-align:right;font-size:12px;white-space:nowrap}
.autorizacion{margin-top:10px;text-align:right}
.autorizacion span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#5b6875}
.autorizacion code{font-size:12px;word-break:break-all;font-family:"SFMono-Regular",Consolas,monospace}
.receptor{margin:14px 0}
.receptor table{border:1px solid #d6dce4}
.receptor th{background:#f4f6f9;text-align:left;padding:5px 8px;font-size:11px;color:#5b6875;white-space:nowrap;width:1%}
.receptor td{padding:5px 8px;border-bottom:1px solid #eef1f5}
.detalle{margin-top:8px;font-size:12px}
.detalle thead th{background:{$color};color:#fff;padding:6px 8px;text-align:left;font-size:11px;letter-spacing:.03em}
.detalle tbody td{padding:5px 8px;border-bottom:1px solid #e6eaef;vertical-align:top}
.detalle .c{text-align:center;white-space:nowrap}
.detalle .d,.d{text-align:right;white-space:nowrap}
.totales{display:flex;gap:24px;margin-top:14px;align-items:flex-start}
.letras{flex:1 1 60%;font-size:12px}
.letras span{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#5b6875}
.letras p{font-weight:600}
.totales table{flex:0 0 38%;width:38%;font-size:12px}
.totales th{text-align:left;padding:3px 8px;color:#5b6875;font-weight:500}
.totales td{text-align:right;padding:3px 8px;white-space:nowrap}
.grantotal th,.grantotal td{border-top:2px solid #16202c;font-size:15px;font-weight:700;padding-top:6px}
.frases{margin-top:14px;font-size:11px;color:#3c4855}
.frases ul{margin:0;padding-left:18px}
footer{margin-top:18px;border-top:1px solid #d6dce4;padding-top:12px;font-size:10.5px;color:#5b6875;display:flex;gap:18px;align-items:flex-start}
.qr{flex:0 0 auto;text-align:center}
.qr svg{display:block}
.qr span{display:block;margin-top:4px;font-size:9px;line-height:1.3}
.pie{flex:1 1 auto}
footer .obs{margin-top:6px;font-style:italic}
.aviso{background:#fff4d6;border:1px solid #e0b32c;color:#7a5a00;padding:8px 12px;border-radius:4px;margin-bottom:14px;font-weight:600;font-size:12px}
.anulado{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;font-size:110px;font-weight:800;color:rgba(200,30,40,.16);transform:rotate(-24deg);pointer-events:none;letter-spacing:.1em;z-index:5}
@media print{
  body{background:#fff;padding:0}
  .hoja{box-shadow:none;border-radius:0;max-width:none;padding:12mm}
  .anulado{position:absolute;color:rgba(200,30,40,.2)}
}
CSS;
    }

    private function estilosTicket(): string
    {
        return <<<CSS
*{box-sizing:border-box}
body{margin:0;padding:10px;background:#e9ecef;
     font:11px/1.35 "Courier New",Courier,monospace;color:#000}
.rollo{width:76mm;margin:0 auto;background:#fff;padding:4mm 3mm;box-shadow:0 1px 8px rgba(0,0,0,.15)}
p{margin:1px 0;word-wrap:break-word}
hr{border:0;border-top:1px dashed #000;margin:5px 0}
.centro{text-align:center}
.fuerte{font-weight:700}
.rot{font-weight:700}
.logo{max-height:48px;max-width:60mm;margin:0 auto 4px;display:block}
table{width:100%;border-collapse:collapse;font-size:10.5px}
.detalle th{text-align:left;border-bottom:1px solid #000;padding:2px 1px;font-size:10px}
.detalle td{padding:2px 1px;vertical-align:top}
.detalle .desc{word-break:break-word}
.detalle .c{text-align:center;white-space:nowrap}
.d{text-align:right;white-space:nowrap}
.totales td{padding:1px}
.totales .grande td{font-size:13px;font-weight:700;border-top:1px solid #000;padding-top:3px}
.letras{margin-top:5px;font-size:10px;font-weight:700}
.frase{margin-top:5px;font-size:9.5px;text-align:center}
.uuid{font-size:9.5px;word-break:break-all;margin-top:2px}
.qr{text-align:center;margin-top:8px}
.qr svg{display:inline-block}
.mini{font-size:8.5px;margin-top:3px}
.alerta{text-align:center;font-weight:700;border:1px solid #000;padding:3px;margin-bottom:5px;font-size:10px}
@media print{
  @page{size:80mm auto;margin:0}
  body{background:#fff;padding:0}
  .rollo{width:80mm;box-shadow:none;padding:2mm 3mm}
}
CSS;
    }
}
