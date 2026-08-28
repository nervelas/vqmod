<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Ajustes;
use App\Core\DB;
use App\Core\Url;
use Vendor\Pdf\Pdf;
use Vendor\Qr\QrCode;

/**
 * Documentos PDF institucionales: recibos, estados de cuenta, solvencias,
 * cartas de cobro, morosidad, informe de asamblea y actas de votación.
 */
final class Documento
{
    private static function verde(): string { return Ajustes::get('color_primario', '#0E4C5A'); }
    private static function oro(): string   { return Ajustes::get('color_acento', '#B94E27'); }

    /** Documento base con encabezado y pie institucionales. */
    private static function base(string $titulo, string $subtitulo = '', string $formato = 'A4'): Pdf
    {
        $pdf = new Pdf($formato);
        $pdf->info($titulo, Ajustes::get('nombre', 'ResidencialPro'), $subtitulo);
        $pdf->margenes(15, 40, 15, 20);

        $logo = Ajustes::get('logo', '');
        $rutaLogo = $logo !== '' ? RUTA_BASE . '/uploads/logos/' . basename($logo) : '';

        $pdf->alEncabezado(static function (Pdf $d) use ($titulo, $subtitulo, $rutaLogo): void {
            $d->colorRelleno(self::verde());
            $d->rectangulo(0, 0, $d->ancho(), 30, 'F');
            $d->colorRelleno(self::oro());
            $d->rectangulo(0, 30, $d->ancho(), 1.2, 'F');
            $x = 15;
            if ($rutaLogo !== '' && is_file($rutaLogo)) {
                if ($d->imagen($rutaLogo, 15, 7, 16, 16)) {
                    $x = 35;
                }
            }
            $d->fuente('times', 'B', 17);
            $d->colorTexto(self::oro());
            $d->texto($x, 14, Ajustes::get('nombre', 'ResidencialPro'));
            $d->fuente('helvetica', '', 7.6);
            $d->colorTexto('#D8E0D9');
            $linea = trim(Ajustes::get('direccion', ''));
            $nit   = Ajustes::get('nit', '');
            if ($nit !== '') {
                $linea = ($linea !== '' ? $linea . '  ·  ' : '') . 'NIT ' . $nit;
            }
            $d->texto($x, 19.5, $linea);
            $tel = Ajustes::get('telefono', '');
            $cor = Ajustes::get('correo', '');
            $d->texto($x, 24, trim(($tel !== '' ? 'Tel. ' . $tel : '') . ($cor !== '' ? '  ·  ' . $cor : '')));

            $d->fuente('helvetica', 'B', 10.5);
            $d->colorTexto('#FFFFFF');
            $d->textoDerecha($d->ancho() - 15, 15, mb_strtoupper($titulo));
            if ($subtitulo !== '') {
                $d->fuente('helvetica', '', 8);
                $d->colorTexto('#C8D2C9');
                $d->textoDerecha($d->ancho() - 15, 20.5, $subtitulo);
            }
            $d->setXY(15, 40);
            $d->colorTexto('#22271F');
        });

        $pdf->alPie(static function (Pdf $d): void {
            $y = $d->alto() - 14;
            $d->colorTrazo('#DCD6C8');
            $d->linea(15, $y, $d->ancho() - 15, $y);
            $d->fuente('helvetica', '', 7);
            $d->colorTexto('#6E6A61');
            $d->texto(15, $y + 4.5, Ajustes::get('nombre', 'ResidencialPro') . ' · Documento generado el ' . date('d/m/Y H:i'));
            $d->textoDerecha($d->ancho() - 15, $y + 4.5, 'Página ' . $d->paginas() . ' de ' . $d->totalPaginasAlias());
        });
        return $pdf;
    }

    /** Bloque de datos de la vivienda. */
    private static function bloqueCasa(Pdf $pdf, array $casa, ?array $residente): void
    {
        $y = $pdf->getY();
        $pdf->colorRelleno('#F6F3EC');
        $pdf->rectRedondo(15, $y, $pdf->ancho() - 30, 22, 3, 'F');
        $pdf->fuente('helvetica', '', 7.5);
        $pdf->colorTexto('#6E6A61');
        $pdf->texto(20, $y + 6.5, 'VIVIENDA');
        $pdf->texto(78, $y + 6.5, 'RESIDENTE');
        $pdf->texto(140, $y + 6.5, 'EMITIDO');
        $pdf->fuente('helvetica', 'B', 10);
        $pdf->colorTexto(self::verde());
        $pdf->texto(20, $y + 12.5, (string) $casa['codigo']);
        $pdf->texto(78, $y + 12.5, mb_substr((string) ($residente['nombre'] ?? 'No registrado'), 0, 32));
        $pdf->texto(140, $y + 12.5, date('d/m/Y'));
        $pdf->fuente('helvetica', '', 7.8);
        $pdf->colorTexto('#5B6259');
        $pdf->texto(20, $y + 17.5, trim((string) ($casa['fase'] ?? '') . (!empty($casa['calle']) ? ' · ' . $casa['calle'] : '')));
        $pdf->texto(78, $y + 17.5, mb_substr((string) ($residente['telefono'] ?? ''), 0, 30));
        $pdf->texto(140, $y + 17.5, (string) ($casa['metros'] > 0 ? $casa['metros'] . ' m² · coef. ' . $casa['coeficiente'] . '%' : ''));
        $pdf->setY($y + 28);
        $pdf->colorTexto('#22271F');
    }

    // ------------------------------------------------------------- RECIBO

    public static function recibo(int $pagoId): string
    {
        $pago = Pago::porId($pagoId);
        if ($pago === null) {
            throw new \RuntimeException('El pago no existe.');
        }
        $casa      = Casa::porId((int) $pago['casa_id']) ?? [];
        $residente = Casa::propietario((int) $pago['casa_id']);
        $detalle   = Pago::detalle($pagoId);

        $pdf = self::base('Recibo de pago', 'No. ' . ($pago['recibo'] ?? '—'));
        $pdf->agregarPagina();
        self::bloqueCasa($pdf, $casa, $residente);

        $pdf->fuente('times', 'B', 14);
        $pdf->colorTexto(self::verde());
        $pdf->texto(15, $pdf->getY(), 'Recibo No. ' . ($pago['recibo'] ?? '—'));
        $pdf->fuente('helvetica', '', 9);
        $pdf->colorTexto('#5B6259');
        $pdf->textoDerecha($pdf->ancho() - 15, $pdf->getY(), 'Fecha de pago: ' . fecha((string) $pago['fecha']));
        $pdf->setY($pdf->getY() + 8);

        $cols = [
            ['titulo' => 'Concepto aplicado', 'ancho' => 120],
            ['titulo' => 'Monto', 'ancho' => 60, 'alinear' => 'D'],
        ];
        $filas = [];
        foreach ($detalle as $d) {
            $filas[] = [(string) $d['concepto'], q((float) $d['monto'])];
        }
        if ($filas === []) {
            $filas[] = ['Pago registrado', q((float) $pago['monto'])];
        }
        $pdf->tabla($cols, $filas, ['alto' => 7.5, 'cabecera' => self::verde()]);

        $y = $pdf->getY() + 3;
        $pdf->colorRelleno('#F1EADA');
        $pdf->rectRedondo(105, $y, 90, 13, 2.5, 'F');
        $pdf->fuente('helvetica', 'B', 11);
        $pdf->colorTexto(self::verde());
        $pdf->texto(110, $y + 8.5, 'TOTAL RECIBIDO');
        $pdf->fuente('helvetica', 'B', 13);
        $pdf->textoDerecha(190, $y + 8.8, q((float) $pago['monto']));
        $pdf->setY($y + 20);

        $pdf->fuente('helvetica', '', 9);
        $pdf->colorTexto('#3A413A');
        $metodos = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia bancaria', 'deposito' => 'Depósito bancario',
                    'tarjeta' => 'Tarjeta', 'linea' => 'Pago en línea', 'otro' => 'Otro'];
        $pdf->setX(15);
        $pdf->parrafo('Forma de pago: ' . ($metodos[$pago['metodo']] ?? $pago['metodo'])
            . (!empty($pago['referencia']) ? '   ·   Referencia: ' . $pago['referencia'] : '')
            . (!empty($pago['banco']) ? '   ·   Banco: ' . $pago['banco'] : ''), 180, 5);

        $saldo = Casa::saldo((int) $pago['casa_id']);
        $pdf->saltar(2);
        $pdf->fuente('helvetica', 'B', 9.5);
        $pdf->colorTexto($saldo > 0.009 ? '#93251E' : '#47713F');
        $pdf->parrafo($saldo > 0.009
            ? 'Saldo pendiente después de este pago: ' . q($saldo)
            : 'La vivienda se encuentra SOLVENTE a la fecha de emisión de este recibo.', 180, 5);

        // Código QR de verificación
        $verif = (string) ($pago['verificacion'] ?? '');
        if ($verif !== '') {
            $y = max($pdf->getY() + 6, $pdf->alto() - 78);
            $url = Url::absoluta('/verificar/' . $verif);
            $pdf->matriz(QrCode::matriz($url, 'M'), 15, $y, 30);
            $pdf->fuente('helvetica', 'B', 8.5);
            $pdf->colorTexto(self::verde());
            $pdf->texto(50, $y + 8, 'Verificación de autenticidad');
            $pdf->fuente('helvetica', '', 7.6);
            $pdf->colorTexto('#5B6259');
            $pdf->setXY(50, $y + 9);
            $pdf->parrafo('Escanee el código con la cámara de su teléfono para confirmar en línea que este recibo fue emitido por la administración. Código: ' . strtoupper(substr($verif, 0, 12)), 100, 4.2);
        }
        self::firmaAdministrador($pdf);
        return $pdf->salida();
    }

    private static function firmaAdministrador(Pdf $pdf): void
    {
        $firma  = Ajustes::get('firma_archivo', '');
        $nombre = Ajustes::get('firma_nombre', '');
        $cargo  = Ajustes::get('firma_cargo', 'Administración');
        if ($nombre === '') {
            return;
        }
        $y = $pdf->alto() - 48;
        if ($firma !== '' && is_file(RUTA_BASE . '/uploads/logos/' . basename($firma))) {
            $pdf->imagen(RUTA_BASE . '/uploads/logos/' . basename($firma), 125, $y - 14, 45);
        }
        $pdf->colorTrazo('#6E6A61');
        $pdf->linea(120, $y, 195, $y);
        $pdf->fuente('helvetica', 'B', 9);
        $pdf->colorTexto(self::verde());
        $pdf->textoCentrado(157.5, $y + 5, $nombre);
        $pdf->fuente('helvetica', '', 7.8);
        $pdf->colorTexto('#6E6A61');
        $pdf->textoCentrado(157.5, $y + 9.5, $cargo);
    }

    // ------------------------------------------------- ESTADO DE CUENTA

    public static function estadoCuenta(int $casaId): string
    {
        $casa = Casa::porId($casaId);
        if ($casa === null) {
            throw new \RuntimeException('La vivienda no existe.');
        }
        Cuota::recalcularMora($casaId);
        $residente = Casa::propietario($casaId);
        $cargos    = Cuota::cargos($casaId, 'pendientes');
        $ant       = Cuota::antiguedad($casaId);
        $aFavor    = Pago::saldoAFavor($casaId);

        $pdf = self::base('Estado de cuenta', $casa['codigo']);
        $pdf->agregarPagina();
        self::bloqueCasa($pdf, $casa, $residente);

        $cols = [
            ['titulo' => 'Concepto', 'ancho' => 78],
            ['titulo' => 'Vence', 'ancho' => 24, 'alinear' => 'C'],
            ['titulo' => 'Cargo', 'ancho' => 25, 'alinear' => 'D'],
            ['titulo' => 'Mora', 'ancho' => 22, 'alinear' => 'D'],
            ['titulo' => 'Abonado', 'ancho' => 24, 'alinear' => 'D'],
            ['titulo' => 'Saldo', 'ancho' => 27, 'alinear' => 'D', 'negrita' => true],
        ];
        $filas = [];
        foreach ($cargos as $c) {
            $filas[] = [
                (string) $c['descripcion'],
                fecha((string) $c['fecha_vence']),
                q((float) $c['monto']),
                (float) $c['mora'] > 0 ? q((float) $c['mora']) : '—',
                (float) $c['pagado'] > 0 ? q((float) $c['pagado']) : '—',
                q(Cuota::saldoCargo($c)),
            ];
        }
        if ($filas === []) {
            $filas[] = ['No existen cargos pendientes.', '', '', '', '', q(0)];
        }
        $pdf->tabla($cols, $filas, ['alto' => 7, 'cabecera' => self::verde()]);

        $pdf->saltar(4);
        $pdf->asegurarEspacio(50);
        $y = $pdf->getY();
        $pdf->colorRelleno('#F6F3EC');
        $pdf->rectRedondo(15, $y, 100, 34, 3, 'F');
        $pdf->fuente('helvetica', 'B', 8.5);
        $pdf->colorTexto(self::verde());
        $pdf->texto(20, $y + 7, 'ANTIGÜEDAD DEL SALDO');
        $pdf->fuente('helvetica', '', 8);
        $pdf->colorTexto('#3A413A');
        $tramos = [
            'Por vencer'     => $ant['corriente'],
            '1 a 30 días'    => $ant['d30'],
            '31 a 60 días'   => $ant['d60'],
            '61 a 90 días'   => $ant['d90'],
            'Más de 90 días' => $ant['d120'],
        ];
        $ty = $y + 13;
        foreach ($tramos as $et => $v) {
            $pdf->texto(20, $ty, $et);
            $pdf->textoDerecha(110, $ty, q($v));
            $ty += 4.6;
        }

        $pdf->colorRelleno(self::verde());
        $pdf->rectRedondo(122, $y, 73, 34, 3, 'F');
        $pdf->fuente('helvetica', '', 8.5);
        $pdf->colorTexto('#C8D2C9');
        $pdf->texto(128, $y + 9, 'SALDO TOTAL A PAGAR');
        $pdf->fuente('times', 'B', 20);
        $pdf->colorTexto(self::oro());
        $pdf->texto(128, $y + 21, q($ant['total']));
        if ($aFavor > 0.009) {
            $pdf->fuente('helvetica', '', 7.5);
            $pdf->colorTexto('#C8D2C9');
            $pdf->texto(128, $y + 28, 'Saldo a favor disponible: ' . q($aFavor));
        }
        $pdf->setY($y + 42);

        $pdf->fuente('helvetica', '', 8.5);
        $pdf->colorTexto('#5B6259');
        $pdf->setX(15);
        $cuenta = Ajustes::get('cuenta_deposito', '');
        $texto  = 'Puede realizar su pago en las oficinas de la administración o por transferencia bancaria.';
        if ($cuenta !== '') {
            $texto .= ' Cuenta para depósitos: ' . $cuenta . '.';
        }
        $texto .= ' Después de pagar, adjunte su comprobante desde el portal del residente para que la administración lo apruebe y reciba su recibo oficial.';
        $pdf->parrafo($texto, 180, 4.6);

        self::firmaAdministrador($pdf);
        return $pdf->salida();
    }

    // ---------------------------------------------------------- SOLVENCIA

    public static function solvencia(int $casaId): string
    {
        $casa = Casa::porId($casaId);
        if ($casa === null) {
            throw new \RuntimeException('La vivienda no existe.');
        }
        Cuota::recalcularMora($casaId);
        $saldo     = Casa::saldo($casaId);
        $residente = Casa::propietario($casaId);
        $codigo    = strtoupper(substr(hash('sha256', $casaId . '|' . date('Y-m-d') . '|' . Ajustes::get('nombre', '')), 0, 12));

        $pdf = self::base('Constancia de solvencia', $casa['codigo']);
        $pdf->agregarPagina();
        $pdf->setY(56);
        $pdf->fuente('times', 'B', 22);
        $pdf->colorTexto(self::verde());
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY(), 'CONSTANCIA DE SOLVENCIA');
        $pdf->colorRelleno(self::oro());
        $pdf->rectangulo($pdf->ancho() / 2 - 25, $pdf->getY() + 4, 50, 0.8, 'F');
        $pdf->setY($pdf->getY() + 18);

        $pdf->fuente('helvetica', '', 11);
        $pdf->colorTexto('#22271F');
        $pdf->setX(20);
        $nombre = (string) ($residente['nombre'] ?? 'el propietario');
        if ($saldo <= 0.009) {
            $texto = 'La administración de ' . Ajustes::get('nombre', 'el residencial') . ' hace constar que la vivienda '
                . $casa['codigo'] . (!empty($casa['fase']) ? ', ubicada en ' . $casa['fase'] : '')
                . (!empty($casa['calle']) ? ', ' . $casa['calle'] : '')
                . ', a nombre de ' . $nombre . ', se encuentra SOLVENTE en el pago de sus cuotas de mantenimiento '
                . 'y demás obligaciones ordinarias y extraordinarias con el residencial, a la fecha de emisión de la presente constancia.';
        } else {
            $texto = 'La administración de ' . Ajustes::get('nombre', 'el residencial') . ' hace constar que la vivienda '
                . $casa['codigo'] . ', a nombre de ' . $nombre . ', presenta un saldo pendiente de ' . q($saldo)
                . ' a la fecha de emisión del presente documento, por lo que NO puede extenderse constancia de solvencia.';
        }
        $pdf->parrafo($texto, 170, 6.5);
        $pdf->saltar(6);
        $pdf->setX(20);
        $pdf->parrafo('Se extiende la presente a solicitud del interesado, para los usos legales que estime convenientes, '
            . 'en ' . Ajustes::get('ciudad', 'la ciudad de Guatemala') . ', el ' . fechaLarga(date('Y-m-d')) . '.', 170, 6.5);

        $y = $pdf->getY() + 14;
        $pdf->colorRelleno($saldo <= 0.009 ? '#EAF3EC' : '#F7ECEC');
        $pdf->rectRedondo(20, $y, 170, 20, 3, 'F');
        $pdf->fuente('helvetica', 'B', 12);
        $pdf->colorTexto($saldo <= 0.009 ? '#47713F' : '#93251E');
        $pdf->textoCentrado($pdf->ancho() / 2, $y + 13, $saldo <= 0.009 ? 'VIVIENDA SOLVENTE' : 'VIVIENDA CON SALDO PENDIENTE: ' . q($saldo));
        $pdf->setY($y + 30);

        $pdf->matriz(QrCode::matriz(Url::absoluta('/verificar/solvencia/' . $casaId . '/' . $codigo), 'M'), 20, $pdf->getY(), 26);
        $pdf->fuente('helvetica', '', 7.5);
        $pdf->colorTexto('#6E6A61');
        $pdf->texto(50, $pdf->getY() + 10, 'Código de verificación: ' . $codigo);
        $pdf->texto(50, $pdf->getY() + 14.5, 'Validez: 30 días a partir de la fecha de emisión.');

        self::firmaAdministrador($pdf);
        return $pdf->salida();
    }

    // ------------------------------------------------------ CARTA DE COBRO

    public static function cartaCobro(int $casaId): string
    {
        $casa = Casa::porId($casaId);
        if ($casa === null) {
            throw new \RuntimeException('La vivienda no existe.');
        }
        Cuota::recalcularMora($casaId);
        $residente = Casa::propietario($casaId);
        $cargos    = Cuota::cargos($casaId, 'pendientes');
        $ant       = Cuota::antiguedad($casaId);
        $dias      = Casa::diasMora($casaId);

        $pdf = self::base('Requerimiento de pago', $casa['codigo']);
        $pdf->agregarPagina();
        $pdf->setY(48);
        $pdf->fuente('helvetica', '', 10);
        $pdf->colorTexto('#22271F');
        $pdf->setX(15);
        $pdf->parrafo(Ajustes::get('ciudad', 'Guatemala') . ', ' . fechaLarga(date('Y-m-d')), 180, 6);
        $pdf->saltar(4);
        $pdf->fuente('helvetica', 'B', 11);
        $pdf->setX(15);
        $pdf->parrafo('Señor(a) ' . ($residente['nombre'] ?? 'Propietario'), 180, 6);
        $pdf->fuente('helvetica', '', 10);
        $pdf->setX(15);
        $pdf->parrafo('Vivienda ' . $casa['codigo'] . (!empty($casa['fase']) ? ' · ' . $casa['fase'] : ''), 180, 5.5);
        $pdf->saltar(6);
        $pdf->setX(15);
        $pdf->parrafo('Estimado(a) propietario(a):', 180, 6);
        $pdf->saltar(2);
        $pdf->setX(15);
        $pdf->parrafo('Por este medio le informamos que, según nuestros registros contables, su vivienda mantiene un saldo pendiente de '
            . q($ant['total']) . ', con una antigüedad de ' . $dias . ' días. Le solicitamos atentamente regularizar su situación '
            . 'dentro de los próximos ' . (int) Ajustes::num('carta_plazo_dias', 15) . ' días hábiles.', 180, 5.8);
        $pdf->saltar(4);

        $cols = [
            ['titulo' => 'Concepto', 'ancho' => 100],
            ['titulo' => 'Vencimiento', 'ancho' => 35, 'alinear' => 'C'],
            ['titulo' => 'Saldo', 'ancho' => 45, 'alinear' => 'D'],
        ];
        $filas = [];
        foreach ($cargos as $c) {
            $filas[] = [(string) $c['descripcion'], fecha((string) $c['fecha_vence']), q(Cuota::saldoCargo($c))];
        }
        $pdf->tabla($cols, $filas, ['alto' => 6.8, 'cabecera' => '#93251E']);

        $pdf->saltar(6);
        $pdf->setX(15);
        $pdf->fuente('helvetica', '', 10);
        $pdf->colorTexto('#22271F');
        $pdf->parrafo(Ajustes::get('carta_texto',
            'De no recibir su pago en el plazo indicado, la administración procederá conforme al reglamento interno del residencial, '
            . 'lo que puede incluir la restricción de servicios no esenciales y el traslado del caso a la asesoría legal del condominio. '
            . 'Si ya realizó su pago, agradecemos remitir el comprobante para actualizar sus registros.'), 180, 5.8);
        $pdf->saltar(6);
        $pdf->setX(15);
        $pdf->parrafo('Agradecemos su atención y quedamos a sus órdenes para acordar un plan de pago si así lo requiere.', 180, 5.8);
        $pdf->saltar(4);
        $pdf->setX(15);
        $pdf->parrafo('Atentamente,', 180, 6);
        self::firmaAdministrador($pdf);
        return $pdf->salida();
    }

    // --------------------------------------------------------- MOROSIDAD

    public static function morosidad(array $filtros = []): string
    {
        $filas   = Cuota::morosidad($filtros);
        $resumen = Cuota::resumenMorosidad($filas);

        $pdf = self::base('Reporte de morosidad', 'Al ' . date('d/m/Y'), 'A4');
        $pdf->agregarPagina();

        $y = $pdf->getY();
        $pdf->colorRelleno('#F6F3EC');
        $pdf->rectRedondo(15, $y, 180, 20, 3, 'F');
        $pdf->fuente('helvetica', '', 7.5);
        $pdf->colorTexto('#6E6A61');
        $et = ['Por vencer' => 'corriente', '1-30 días' => 'd30', '31-60 días' => 'd60', '61-90 días' => 'd90', '+90 días' => 'd120', 'TOTAL' => 'total'];
        $x = 20;
        foreach ($et as $nombre => $clave) {
            $pdf->fuente('helvetica', '', 7.2);
            $pdf->colorTexto('#6E6A61');
            $pdf->texto($x, $y + 7, mb_strtoupper($nombre));
            $pdf->fuente('helvetica', 'B', $clave === 'total' ? 11 : 9.5);
            $pdf->colorTexto($clave === 'total' ? '#93251E' : self::verde());
            $pdf->texto($x, $y + 14, q((float) $resumen[$clave]));
            $x += 29;
        }
        $pdf->setY($y + 26);
        $pdf->fuente('helvetica', '', 9);
        $pdf->colorTexto('#5B6259');
        $pdf->texto(15, $pdf->getY(), $resumen['casas'] . ' viviendas con saldo pendiente');
        $pdf->setY($pdf->getY() + 5);

        $cols = [
            ['titulo' => 'Casa', 'ancho' => 20],
            ['titulo' => 'Fase / calle', 'ancho' => 38],
            ['titulo' => 'Residente', 'ancho' => 48],
            ['titulo' => 'Vence', 'ancho' => 20, 'alinear' => 'C'],
            ['titulo' => 'Días', 'ancho' => 14, 'alinear' => 'C'],
            ['titulo' => 'Mora', 'ancho' => 18, 'alinear' => 'D'],
            ['titulo' => 'Saldo', 'ancho' => 22, 'alinear' => 'D', 'negrita' => true],
        ];
        $datos = [];
        foreach ($filas as $f) {
            $datos[] = [
                (string) $f['codigo'],
                trim((string) $f['fase'] . (!empty($f['calle']) ? ' · ' . $f['calle'] : '')),
                (string) ($f['residente'] ?? '—'),
                fecha((string) $f['vence']),
                (string) $f['dias'],
                q((float) $f['mora']),
                q((float) $f['saldo']),
            ];
        }
        if ($datos === []) {
            $datos[] = ['—', 'No hay viviendas con saldo pendiente.', '', '', '', '', ''];
        }
        $pdf->tabla($cols, $datos, ['alto' => 6.4, 'tam_fila' => 7.6, 'cabecera' => self::verde()]);
        return $pdf->salida();
    }

    // ------------------------------------------------- INFORME DE ASAMBLEA

    public static function informeMensual(string $periodo): string
    {
        $desde = $periodo . '-01';
        $hasta = date('Y-m-t', (int) strtotime($desde));
        $ingresos = Pago::recaudado($desde, $hasta);
        $egresos  = Egreso::total($desde, $hasta);
        $esperado = Cuota::esperadoPeriodo($periodo);
        $porCat   = Egreso::porCategoria($desde, $hasta);
        $porMet   = Pago::porMetodo($desde, $hasta);
        $cuentas  = Egreso::saldosCuentas();
        $moros    = Cuota::resumenMorosidad(Cuota::morosidad());

        $pdf = self::base('Informe mensual', periodoNombre($periodo));
        $pdf->agregarPagina();

        $tarjetas = [
            ['Ingresos del mes', q($ingresos), self::verde()],
            ['Egresos del mes', q($egresos), '#93251E'],
            ['Resultado', q($ingresos - $egresos), $ingresos - $egresos >= 0 ? '#47713F' : '#93251E'],
        ];
        $x = 15;
        $y = $pdf->getY();
        foreach ($tarjetas as [$titulo, $valor, $color]) {
            $pdf->colorRelleno('#F6F3EC');
            $pdf->rectRedondo($x, $y, 57, 24, 3, 'F');
            $pdf->fuente('helvetica', '', 7.5);
            $pdf->colorTexto('#6E6A61');
            $pdf->texto($x + 5, $y + 8, mb_strtoupper($titulo));
            $pdf->fuente('times', 'B', 15);
            $pdf->colorTexto($color);
            $pdf->texto($x + 5, $y + 18, $valor);
            $x += 62;
        }
        $pdf->setY($y + 32);

        $pdf->fuente('helvetica', 'B', 10);
        $pdf->colorTexto(self::verde());
        $pdf->texto(15, $pdf->getY(), 'Cobranza del período');
        $pdf->setY($pdf->getY() + 4);
        $efec = $esperado > 0 ? round($ingresos * 100 / $esperado, 1) : 0;
        $pdf->tabla(
            [['titulo' => 'Indicador', 'ancho' => 120], ['titulo' => 'Valor', 'ancho' => 60, 'alinear' => 'D']],
            [
                ['Emisión esperada del período', q($esperado)],
                ['Recaudación efectiva', q($ingresos)],
                ['Efectividad de cobro', $efec . ' %'],
                ['Cartera vencida total del residencial', q((float) $moros['total'])],
                ['Viviendas con saldo pendiente', (string) $moros['casas']],
            ],
            ['alto' => 6.6, 'cabecera' => self::verde()]
        );

        $pdf->saltar(6);
        $pdf->asegurarEspacio(40);
        $pdf->fuente('helvetica', 'B', 10);
        $pdf->colorTexto(self::verde());
        $pdf->texto(15, $pdf->getY(), 'Egresos por categoría');
        $pdf->setY($pdf->getY() + 4);
        $f = [];
        foreach ($porCat as $c) {
            $f[] = [(string) $c['categoria'], q((float) $c['total']),
                    $egresos > 0 ? round((float) $c['total'] * 100 / $egresos, 1) . ' %' : '0 %'];
        }
        if ($f === []) {
            $f[] = ['Sin egresos registrados en el período', q(0), '0 %'];
        }
        $pdf->tabla(
            [['titulo' => 'Categoría', 'ancho' => 110], ['titulo' => 'Monto', 'ancho' => 40, 'alinear' => 'D'],
             ['titulo' => '% del total', 'ancho' => 30, 'alinear' => 'D']],
            $f,
            ['alto' => 6.6, 'cabecera' => self::verde()]
        );

        $pdf->saltar(6);
        $pdf->asegurarEspacio(40);
        $pdf->fuente('helvetica', 'B', 10);
        $pdf->colorTexto(self::verde());
        $pdf->texto(15, $pdf->getY(), 'Ingresos por forma de pago');
        $pdf->setY($pdf->getY() + 4);
        $f = [];
        foreach ($porMet as $m) {
            $f[] = [ucfirst((string) $m['metodo']), (string) $m['n'], q((float) $m['total'])];
        }
        if ($f === []) {
            $f[] = ['Sin pagos registrados', '0', q(0)];
        }
        $pdf->tabla(
            [['titulo' => 'Forma de pago', 'ancho' => 110], ['titulo' => 'Operaciones', 'ancho' => 35, 'alinear' => 'C'],
             ['titulo' => 'Monto', 'ancho' => 35, 'alinear' => 'D']],
            $f,
            ['alto' => 6.6, 'cabecera' => self::verde()]
        );

        $pdf->saltar(6);
        $pdf->asegurarEspacio(40);
        $pdf->fuente('helvetica', 'B', 10);
        $pdf->colorTexto(self::verde());
        $pdf->texto(15, $pdf->getY(), 'Saldos en caja y bancos');
        $pdf->setY($pdf->getY() + 4);
        $f = [];
        foreach ($cuentas as $c) {
            $f[] = [(string) $c['nombre'], ucfirst((string) $c['tipo']), q((float) $c['saldo'])];
        }
        if ($f === []) {
            $f[] = ['Sin cuentas configuradas', '', q(0)];
        }
        $pdf->tabla(
            [['titulo' => 'Cuenta', 'ancho' => 110], ['titulo' => 'Tipo', 'ancho' => 35, 'alinear' => 'C'],
             ['titulo' => 'Saldo', 'ancho' => 35, 'alinear' => 'D', 'negrita' => true]],
            $f,
            ['alto' => 6.6, 'cabecera' => self::verde()]
        );

        self::firmaAdministrador($pdf);
        return $pdf->salida();
    }

    // ------------------------------------------------------------- ACTA

    public static function actaVotacion(int $votacionId): string
    {
        $v = Comunicacion::votacion($votacionId);
        if ($v === null) {
            throw new \RuntimeException('La votación no existe.');
        }
        $r = Comunicacion::resultados($votacionId);

        $pdf = self::base('Acta de votación', mb_substr((string) $v['titulo'], 0, 60));
        $pdf->agregarPagina();
        $pdf->fuente('times', 'B', 16);
        $pdf->colorTexto(self::verde());
        $pdf->setX(15);
        $pdf->parrafo((string) $v['titulo'], 180, 8);
        $pdf->saltar(2);
        $pdf->fuente('helvetica', '', 9.5);
        $pdf->colorTexto('#3A413A');
        $pdf->setX(15);
        $pdf->parrafo((string) ($v['detalle'] ?? ''), 180, 5.4);
        $pdf->saltar(4);
        $pdf->setX(15);
        $pdf->parrafo('Modalidad de votación: ' . ($v['modo'] === 'coeficiente' ? 'ponderada por coeficiente de participación' : 'una vivienda, un voto')
            . '. Período: del ' . fechahora((string) $v['inicio']) . ' al ' . fechahora((string) $v['fin']) . '.', 180, 5.4);
        $pdf->saltar(4);

        $filas = [];
        foreach ($r['opciones'] as $o) {
            $filas[] = [(string) $o['texto'], (string) $o['votos'], number_format((float) $o['peso'], 4), $o['porcentaje'] . ' %'];
        }
        $pdf->tabla(
            [['titulo' => 'Opción', 'ancho' => 96], ['titulo' => 'Viviendas', 'ancho' => 28, 'alinear' => 'C'],
             ['titulo' => 'Peso', 'ancho' => 28, 'alinear' => 'D'], ['titulo' => 'Resultado', 'ancho' => 28, 'alinear' => 'D', 'negrita' => true]],
            $filas,
            ['alto' => 7, 'cabecera' => self::verde()]
        );
        $pdf->saltar(6);
        $pdf->fuente('helvetica', 'B', 10);
        $pdf->colorTexto(self::verde());
        $pdf->setX(15);
        $pdf->parrafo('Participación: ' . $r['votos'] . ' viviendas · Quórum alcanzado: ' . $r['quorum'] . ' % (requerido ' . (float) $v['quorum'] . ' %)', 180, 6);
        $pdf->saltar(2);
        $pdf->fuente('helvetica', '', 9.5);
        $pdf->colorTexto($r['quorum'] >= (float) $v['quorum'] ? '#47713F' : '#93251E');
        $pdf->setX(15);
        $pdf->parrafo($r['quorum'] >= (float) $v['quorum']
            ? 'Se alcanzó el quórum reglamentario, por lo que la decisión adoptada es vinculante conforme al reglamento interno.'
            : 'No se alcanzó el quórum reglamentario requerido para esta votación.', 180, 5.4);
        self::firmaAdministrador($pdf);
        return $pdf->salida();
    }

    /** Hoja de códigos QR de un pre-registro para imprimir o compartir. */
    public static function pasePreregistro(int $preregId): string
    {
        $p = DB::uno('SELECT * FROM preregistros WHERE id = :id', ['id' => $preregId]);
        if ($p === null) {
            throw new \RuntimeException('El pre-registro no existe.');
        }
        $casa = Casa::porId((int) $p['casa_id']) ?? [];
        $pdf  = self::base('Pase de ingreso', (string) $casa['codigo']);
        $pdf->agregarPagina();
        $pdf->setY(52);
        $pdf->fuente('times', 'B', 20);
        $pdf->colorTexto(self::verde());
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY(), 'PASE DE INGRESO');
        $pdf->setY($pdf->getY() + 14);
        $pdf->matriz(QrCode::matriz(Visita::tokenQr($p), 'M'), ($pdf->ancho() - 70) / 2, $pdf->getY(), 70);
        $pdf->setY($pdf->getY() + 78);
        $pdf->fuente('helvetica', '', 9);
        $pdf->colorTexto('#6E6A61');
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY(), 'CÓDIGO NUMÉRICO');
        $pdf->fuente('times', 'B', 30);
        $pdf->colorTexto(self::oro());
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY() + 13, chunk_split((string) $p['codigo'], 3, ' '));
        $pdf->setY($pdf->getY() + 24);
        $pdf->fuente('helvetica', '', 10);
        $pdf->colorTexto('#22271F');
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY(), (string) $p['visitante']);
        $pdf->fuente('helvetica', '', 9);
        $pdf->colorTexto('#5B6259');
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY() + 6, 'Destino: ' . ($casa['codigo'] ?? '') . (!empty($p['placa']) ? '  ·  Placa ' . $p['placa'] : ''));
        $pdf->textoCentrado($pdf->ancho() / 2, $pdf->getY() + 12, 'Vigencia: ' . fechahora((string) $p['valido_desde']) . ' a ' . fechahora((string) $p['valido_hasta']));
        return $pdf->salida();
    }
}
