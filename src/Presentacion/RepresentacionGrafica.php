<?php
declare(strict_types=1);

namespace Fel\Presentacion;

use Fel\Core\Config;
use Fel\Dte\Calculator;
use Fel\Dte\Catalogos;

/**
 * Representacion grafica del DTE (lo que se entrega al cliente).
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
 * Se genera HTML para imprimir o "Guardar como PDF" desde el navegador,
 * sin librerias externas.
 */
final class RepresentacionGrafica
{
    /**
     * @param array<string,mixed> $documento Fila de fel_documentos
     * @param list<array<string,mixed>> $items Filas de fel_documento_items
     * @param list<string> $frases Textos de las frases aplicadas
     */
    public function html(array $documento, array $items, array $frases = []): string
    {
        $e = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $n = static fn (mixed $valor): string => number_format((float) $valor, 2, '.', ',');

        $tipos       = Catalogos::tiposDte();
        $nombreTipo  = $tipos[(string) $documento['tipo']]['nombre'] ?? (string) $documento['tipo'];
        $anulado     = $documento['estado'] === 'ANULADO';
        $certificado = $documento['estado'] === 'CERTIFICADO' || $anulado;

        $emisor = (array) Config::get('emisor', []);
        $moneda = (string) $documento['moneda'];
        $simbolo = $moneda === 'GTQ' ? 'Q' : ($moneda === 'USD' ? '$' : $moneda . ' ');

        $urlVerificacion = (string) Config::get(
            'sat.url_verificador',
            'https://felpub.c.sat.gob.gt/verificador-web/publico/vistas/verificacionDte.jsf'
        );

        $filasItems = '';
        foreach ($items as $item) {
            $filasItems .= sprintf(
                '<tr>
                    <td class="c">%s</td>
                    <td class="c">%s</td>
                    <td class="c">%s</td>
                    <td>%s</td>
                    <td class="d">%s</td>
                    <td class="d">%s</td>
                    <td class="d">%s</td>
                 </tr>',
                $e($item['numero_linea']),
                $e(rtrim(rtrim(number_format((float) $item['cantidad'], 6, '.', ''), '0'), '.')),
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

        $enLetras = Calculator::montoEnLetras((float) $documento['gran_total'], $moneda);

        $marcaAnulado = $anulado
            ? '<div class="anulado">ANULADO</div>'
            : '';

        $avisoSinCertificar = $certificado
            ? ''
            : '<div class="aviso">DOCUMENTO NO CERTIFICADO — estado: ' . $e($documento['estado'])
              . '. Esta impresion NO tiene validez fiscal.</div>';

        $css = $this->estilos();

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
  {$avisoSinCertificar}

  <header class="encabezado">
    <div class="emisor">
      <h1>{$e($emisor['nombre_comercial'] ?? $documento['emisor_nombre'])}</h1>
      <p class="razon">{$e($documento['emisor_nombre'])}</p>
      <p>NIT: <strong>{$e($documento['emisor_nit'])}</strong></p>
      <p>{$e($emisor['direccion'] ?? '')}</p>
      <p>{$e($emisor['municipio'] ?? '')}, {$e($emisor['departamento'] ?? '')}, {$e($emisor['pais'] ?? 'GT')}</p>
      <p>{$e($emisor['correo'] ?? '')} {$e($emisor['telefono'] ?? '')}</p>
      <p>Establecimiento: {$e($documento['establecimiento'])}</p>
    </div>
    <div class="documento">
      <h2>{$e(mb_strtoupper($nombreTipo))}</h2>
      <p class="etiqueta">Documento Tributario Electrónico</p>
      <table class="meta">
        <tr><th>Serie</th><td>{$e($documento['serie'])}</td></tr>
        <tr><th>Número</th><td>{$e($documento['numero'])}</td></tr>
        <tr><th>Fecha de emisión</th><td>{$e($this->fechaLegible((string) $documento['fecha_emision']))}</td></tr>
        <tr><th>Fecha de certificación</th><td>{$e($this->fechaLegible((string) $documento['fecha_certificacion']))}</td></tr>
        <tr><th>Moneda</th><td>{$e($moneda)}</td></tr>
      </table>
      <div class="autorizacion">
        <span>Número de autorización</span>
        <code>{$e($documento['uuid'])}</code>
      </div>
    </div>
  </header>

  <section class="receptor">
    <table>
      <tr>
        <th>NIT / Identificación</th><td>{$e($documento['receptor_id'])}</td>
        <th>Nombre</th><td>{$e($documento['receptor_nombre'])}</td>
      </tr>
      <tr>
        <th>Correo</th><td colspan="3">{$e($documento['receptor_correo'])}</td>
      </tr>
    </table>
  </section>

  <table class="detalle">
    <thead>
      <tr>
        <th>#</th><th>Cant.</th><th>U/M</th><th>Descripción</th>
        <th class="d">P. unitario</th><th class="d">Descuento</th><th class="d">Total</th>
      </tr>
    </thead>
    <tbody>{$filasItems}</tbody>
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

  <section class="frases">
    <ul>{$listaFrases}</ul>
  </section>

  <footer>
    <p><strong>Certificador:</strong> {$e(Config::get('certificador.nombre_visible', $documento['certificador']))}
       — NIT {$e(Config::get('certificador.nit_visible', ''))}</p>
    <p>Consulte la validez de este documento en: {$e($urlVerificacion)}</p>
    <p class="obs">{$e($documento['observaciones'])}</p>
  </footer>
</div>
<script>if (location.search.indexOf('imprimir=1') !== -1) { window.print(); }</script>
</body>
</html>
HTML;
    }

    private function fechaLegible(string $iso): string
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

    private function estilos(): string
    {
        return <<<CSS
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#eef1f5;font:13px/1.45 "Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#16202c}
.hoja{max-width:840px;margin:0 auto;background:#fff;padding:28px 32px;border-radius:6px;box-shadow:0 2px 14px rgba(0,0,0,.09);position:relative}
h1{font-size:19px;margin:0 0 2px}
h2{font-size:17px;margin:0;letter-spacing:.06em}
p{margin:1px 0}
.encabezado{display:flex;gap:24px;justify-content:space-between;border-bottom:2px solid #16202c;padding-bottom:14px}
.emisor{flex:1 1 55%;font-size:12px}
.emisor .razon{font-weight:600}
.documento{flex:1 1 45%;text-align:right}
.etiqueta{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#5b6875;margin-bottom:8px}
table{border-collapse:collapse;width:100%}
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
.detalle thead th{background:#16202c;color:#fff;padding:6px 8px;text-align:left;font-size:11px;letter-spacing:.03em}
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
.grantotal th,.grantotal td{border-top:2px solid #16202c;font-size:15px;font-weight:700;color:#16202c;padding-top:6px}
.frases{margin-top:14px;font-size:11px;color:#3c4855}
.frases ul{margin:0;padding-left:18px}
footer{margin-top:18px;border-top:1px solid #d6dce4;padding-top:10px;font-size:10.5px;color:#5b6875}
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
}
