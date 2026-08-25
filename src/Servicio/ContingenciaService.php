<?php
declare(strict_types=1);

namespace Fel\Servicio;

use Fel\Core\Config;
use Fel\Core\Logger;
use Fel\Repositorio\DocumentoRepositorio;

/**
 * Reintenta los documentos que quedaron en contingencia porque el
 * certificador o el enlace a internet no estaban disponibles.
 *
 * Pensado para ejecutarse desde un cron de cPanel cada pocos minutos:
 *   *\/10 * * * * /usr/local/bin/php /home/USUARIO/fel/bin/reintentar_pendientes.php
 */
final class ContingenciaService
{
    public function __construct(
        private ?FacturacionService $facturacion = null,
        private ?DocumentoRepositorio $documentos = null,
    ) {
        $this->facturacion ??= new FacturacionService();
        $this->documentos  ??= new DocumentoRepositorio();
    }

    /** @return array{procesados:int,certificados:int,fallidos:int,detalle:list<string>} */
    public function procesarPendientes(int $limite = 50): array
    {
        $maximo    = (int) Config::get('reglas.maximo_intentos', 10);
        $pendientes = $this->documentos->pendientes($limite, $maximo);

        $certificados = 0;
        $fallidos     = 0;
        $detalle      = [];

        foreach ($pendientes as $fila) {
            $documentoId = (int) $fila['id'];
            $resultado   = $this->facturacion->reintentar($documentoId);

            if ($resultado->exito) {
                $certificados++;
                $detalle[] = sprintf('Documento %d certificado. UUID %s', $documentoId, $resultado->uuid());
                continue;
            }

            $fallidos++;
            $detalle[] = sprintf('Documento %d sigue pendiente: %s', $documentoId, $resultado->mensaje());
        }

        Logger::info('Proceso de contingencia', [
            'procesados'   => count($pendientes),
            'certificados' => $certificados,
            'fallidos'     => $fallidos,
        ]);

        return [
            'procesados'   => count($pendientes),
            'certificados' => $certificados,
            'fallidos'     => $fallidos,
            'detalle'      => $detalle,
        ];
    }
}
