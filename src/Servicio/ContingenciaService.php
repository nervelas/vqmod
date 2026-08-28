<?php
declare(strict_types=1);

namespace Fel\Servicio;

use Fel\Core\Config;
use Fel\Core\Logger;
use Fel\Repositorio\DocumentoRepositorio;
use Fel\Repositorio\EmpresaRepositorio;

/**
 * Reintenta los documentos que quedaron en contingencia porque el
 * certificador o el enlace a internet no estaban disponibles.
 *
 * Recorre TODAS las empresas activas de la instalacion: es un proceso de
 * plataforma, no de una empresa en particular. Cada documento se reintenta
 * con las credenciales de su propia empresa.
 *
 * Pensado para el cron de cPanel, cada pocos minutos.
 */
final class ContingenciaService
{
    public function __construct(private ?EmpresaRepositorio $empresas = null)
    {
        $this->empresas ??= new EmpresaRepositorio();
    }

    /** @return array{procesados:int,certificados:int,fallidos:int,detalle:list<string>} */
    public function procesarPendientes(int $limite = 100): array
    {
        $maximo     = (int) Config::get('reglas.maximo_intentos', 10);
        $pendientes = DocumentoRepositorio::pendientesGlobales($limite, $maximo);

        $certificados = 0;
        $fallidos     = 0;
        $detalle      = [];
        $servicios    = [];

        foreach ($pendientes as $fila) {
            $empresaId   = (int) $fila['empresa_id'];
            $documentoId = (int) $fila['id'];

            if (!isset($servicios[$empresaId])) {
                $empresa = $this->empresas->buscar($empresaId);

                if ($empresa === null) {
                    $fallidos++;
                    $detalle[] = sprintf('Documento %d: su empresa ya no existe.', $documentoId);
                    continue;
                }

                $servicios[$empresaId] = new FacturacionService($empresa);
            }

            $resultado = $servicios[$empresaId]->reintentar($documentoId);

            if ($resultado->exito) {
                $certificados++;
                $detalle[] = sprintf(
                    'Empresa %d, documento %d certificado. UUID %s',
                    $empresaId,
                    $documentoId,
                    $resultado->uuid()
                );
                continue;
            }

            $fallidos++;
            $detalle[] = sprintf(
                'Empresa %d, documento %d sigue pendiente: %s',
                $empresaId,
                $documentoId,
                $resultado->mensaje()
            );
        }

        Logger::info('Proceso de contingencia', [
            'procesados'   => count($pendientes),
            'certificados' => $certificados,
            'fallidos'     => $fallidos,
            'empresas'     => count($servicios),
        ]);

        return [
            'procesados'   => count($pendientes),
            'certificados' => $certificados,
            'fallidos'     => $fallidos,
            'detalle'      => $detalle,
        ];
    }
}
