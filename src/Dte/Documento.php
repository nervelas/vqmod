<?php
declare(strict_types=1);

namespace Fel\Dte;

use Fel\Core\Validator;

/**
 * Documento Tributario Electronico listo para ser convertido a XML,
 * firmado y certificado.
 */
final class Documento
{
    /** @var list<Item> */
    public array $items = [];

    /** @var list<Frase> */
    public array $frases = [];

    /** @var array<string,string> Datos libres que viajan en la Adenda (no fiscales). */
    public array $adenda = [];

    public ?Referencia $referencia = null;

    /** @var list<array{fecha:string,monto:float,numero:int}> Abonos de factura cambiaria. */
    public array $abonos = [];

    public function __construct(
        public string $tipo,
        public Emisor $emisor,
        public Receptor $receptor,
        public string $moneda = 'GTQ',
        public ?string $fechaEmision = null,
        public float $tipoCambio = 1.0,
        public string $observaciones = '',
        public string $referenciaInterna = '',
    ) {
        $this->tipo         = strtoupper($tipo);
        $this->fechaEmision ??= Validator::fechaHoraSat();
    }

    public function agregarItem(Item $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function agregarFrase(Frase $frase): self
    {
        $this->frases[] = $frase;

        return $this;
    }

    /** Aplica las frases sugeridas si el documento no trae ninguna. */
    public function completarFrases(): self
    {
        if ($this->frases === []) {
            $this->frases = Frase::sugeridasPara($this->emisor->afiliacionIva);
        }

        return $this;
    }

    public function desglosaIva(): bool
    {
        return Catalogos::tipoDteDesglosaIva($this->tipo);
    }

    /**
     * Validacion completa previa al envio. Devolver errores aqui evita
     * rechazos del certificador y anulaciones innecesarias.
     *
     * @return list<string>
     */
    public function validar(): array
    {
        $errores = [];

        if (!Catalogos::tipoDteValido($this->tipo)) {
            $errores[] = 'Tipo de DTE no valido: ' . $this->tipo;
        }
        if (!array_key_exists($this->moneda, Catalogos::monedas())) {
            $errores[] = 'Moneda no reconocida: ' . $this->moneda;
        }
        if ($this->items === []) {
            $errores[] = 'El documento debe tener al menos una linea de detalle.';
        }
        if (count($this->items) > 1000) {
            $errores[] = 'El documento excede el maximo practico de 1000 lineas.';
        }

        $errores = array_merge($errores, $this->emisor->validar(), $this->receptor->validar());

        foreach ($this->items as $indice => $item) {
            $errores = array_merge($errores, $item->validar($indice + 1));
        }

        if ($this->frases === []) {
            $errores[] = 'El DTE debe llevar al menos una frase (TipoFrase/CodigoEscenario).';
        }

        if (Catalogos::tipoDteEsNota($this->tipo) && $this->referencia === null) {
            $errores[] = 'Las notas de credito y debito requieren la referencia al documento origen.';
        }

        if ($this->receptor->esConsumidorFinal()) {
            $limite = (float) \Fel\Core\Config::get('reglas.limite_consumidor_final', 2500.0);
            $total  = Calculator::granTotal($this);
            if ($limite > 0 && $total > $limite && $this->moneda === 'GTQ') {
                $errores[] = sprintf(
                    'Venta a consumidor final por Q%s. A partir de Q%s SAT espera que se identifique al comprador con NIT o CUI.',
                    number_format($total, 2),
                    number_format($limite, 2)
                );
            }
        }

        if ($this->moneda !== 'GTQ' && $this->tipoCambio <= 0) {
            $errores[] = 'Debe indicar el tipo de cambio cuando la moneda no es GTQ.';
        }

        return $errores;
    }
}
