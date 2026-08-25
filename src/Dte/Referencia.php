<?php
declare(strict_types=1);

namespace Fel\Dte;

/**
 * Referencia al documento origen. Obligatoria en notas de credito y debito.
 */
final class Referencia
{
    public function __construct(
        public string $numeroAutorizacionDocumentoOrigen,
        public string $fechaEmisionDocumentoOrigen,
        public string $motivoAjuste,
        public string $numeroDocumentoOrigen = '',
        public string $serieDocumentoOrigen = '',
        /** 1 = referencia a documento electronico (UUID), 2 = documento en papel */
        public int $regimenAntiguo = 1,
    ) {
    }
}
