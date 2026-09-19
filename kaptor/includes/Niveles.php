<?php
/**
 * Kaptor - Detección del nivel educativo de un centro.
 *
 * Un dominio .edu.gt no dice si el centro llega a diversificado o se queda en
 * primaria: eso solo lo dice el texto de su web. Esta clase busca en la página
 * las palabras con las que los propios colegios se describen y devuelve los
 * niveles que ofrecen, para poder escribir solo a los que interesan.
 *
 * Los niveles son los del sistema educativo guatemalteco:
 *   preprimaria · primaria · basicos · diversificado · superior
 */
declare(strict_types=1);

final class Niveles
{
    /** Nombres bonitos para enseñar en la tabla y en el Excel. */
    public const NOMBRES = [
        'preprimaria'   => 'Preprimaria',
        'primaria'      => 'Primaria',
        'basicos'       => 'Básicos',
        'diversificado' => 'Diversificado',
        'superior'      => 'Superior',
    ];

    /**
     * Pistas por nivel. Se comparan sobre el texto ya sin acentos y en
     * minúsculas, así que aquí van sin tildes a propósito.
     *
     * Las carreras de diversificado (bachillerato, perito, secretariado,
     * magisterio) valen más que la palabra "diversificado" en sí: casi ningún
     * colegio dice "diversificado" pero todos anuncian sus carreras.
     */
    private const PISTAS = [
        'preprimaria' => [
            'preprimaria', 'pre-primaria', 'pre primaria', 'parvulos', 'parvularia',
            'kinder', 'kindergarten', 'maternal', 'nursery', 'preescolar', 'pre-escolar',
        ],
        'primaria' => [
            'primaria', 'nivel primario', 'elementary', 'primer grado', 'sexto primaria',
            'sexto grado', 'educacion primaria',
        ],
        'basicos' => [
            'basicos', 'ciclo basico', 'nivel basico', 'educacion basica', 'basica',
            'primero basico', 'segundo basico', 'tercero basico', 'middle school',
            'secundaria', 'ineb', 'telesecundaria',
        ],
        'diversificado' => [
            'diversificado', 'ciclo diversificado', 'bachillerato', 'bachiller',
            'perito contador', 'perito en', 'peritos', 'secretariado', 'secretaria bilingue',
            'magisterio', 'maestra de educacion', 'maestro de educacion', 'high school',
            'cuarto bachillerato', 'quinto bachillerato', 'carreras', 'carrera de',
            'bachilleres', 'contaduria',
        ],
        // Ojo: un colegio de diversificado dice "preparamos para la universidad".
        // Por eso la palabra suelta no basta y aquí van señales de verdad.
        'superior' => [
            'facultad de', 'licenciatura', 'licenciaturas', 'maestria', 'maestrias',
            'doctorado', 'posgrado', 'postgrado', 'campus universitario', 'rectoria',
            'universidad de', 'universidad del', 'universidad nacional', 'universitaria',
            'tecnico universitario', 'profesorado universitario', 'pensum universitario',
        ],
    ];

    /**
     * Cuántas pistas distintas hacen falta para dar un nivel por bueno.
     * "superior" pide dos porque cualquier colegio nombra la universidad de
     * pasada ("preparamos para la universidad") y no por eso es una.
     */
    private const MINIMO = 1;
    private const MINIMOS = ['superior' => 2];

    /**
     * Devuelve los niveles que menciona un texto.
     *
     * @return string[] Ej. ['primaria', 'basicos', 'diversificado']
     */
    public static function detectar(string $texto): array
    {
        $texto = self::normalizar($texto);
        if ($texto === '') { return []; }

        $encontrados = [];
        foreach (self::PISTAS as $nivel => $pistas) {
            $falta    = self::MINIMOS[$nivel] ?? self::MINIMO;
            $aciertos = 0;
            foreach ($pistas as $pista) {
                if (str_contains($texto, $pista)) { $aciertos++; }
                if ($aciertos >= $falta) { break; }
            }
            if ($aciertos >= $falta) { $encontrados[] = $nivel; }
        }

        // "Primaria" aparece dentro de "preprimaria": si solo salió por ahí, no cuenta.
        if (in_array('primaria', $encontrados, true) && in_array('preprimaria', $encontrados, true)) {
            $sinPre = str_replace(['preprimaria', 'pre-primaria', 'pre primaria'], ' ', $texto);
            $vale = false;
            foreach (self::PISTAS['primaria'] as $pista) {
                if (str_contains($sinPre, $pista)) { $vale = true; break; }
            }
            if (!$vale) {
                $encontrados = array_values(array_diff($encontrados, ['primaria']));
            }
        }

        // Igual con "basicos" dentro de "basica" y con "secundaria" dentro de otras
        // palabras: ya se controla con las pistas, no hace falta más.
        return $encontrados;
    }

    /** Une dos listas de niveles sin repetir y en el orden natural. */
    public static function unir(array $a, array $b): array
    {
        $juntos = array_unique(array_merge($a, $b));
        $orden  = array_keys(self::NOMBRES);
        usort($juntos, static fn($x, $y) => array_search($x, $orden, true) <=> array_search($y, $orden, true));
        return array_values($juntos);
    }

    /** "basicos,diversificado" → "Básicos · Diversificado" */
    public static function etiqueta(string $guardados): string
    {
        $lista = array_filter(explode(',', $guardados));
        $txt   = [];
        foreach ($lista as $n) { $txt[] = self::NOMBRES[$n] ?? $n; }
        return implode(' · ', $txt);
    }

    /** ¿Este centro sirve para lo que se busca? */
    public static function tiene(string $guardados, array $buscados): bool
    {
        if (!$buscados) { return true; }
        $lista = array_filter(explode(',', $guardados));
        foreach ($buscados as $b) {
            if (in_array($b, $lista, true)) { return true; }
        }
        return false;
    }

    /** Minúsculas, sin acentos y con un solo espacio entre palabras. */
    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e',
        ]);
        $texto = preg_replace('~\s+~u', ' ', $texto) ?? $texto;
        return $texto;
    }
}
