<?php
/**
 * CorreoRadar - Lista de supresión.
 *
 * Es la lista negra global: cualquier dirección que esté aquí queda excluida de
 * TODAS las campañas, para siempre y sin excepción. Se alimenta sola con las
 * bajas y los rebotes permanentes, y el administrador puede añadir o importar
 * direcciones a mano.
 *
 * Respetar esta lista no es solo buena educación: es lo que evita denuncias y
 * lo que mantiene la reputación del dominio.
 */
declare(strict_types=1);

final class Supresion
{
    /** @var array<string,bool> Cache en memoria para no repetir consultas. */
    private static array $cache = [];

    /** ¿Está esta dirección suprimida? */
    public static function esta(string $correo): bool
    {
        $correo = mb_strtolower(trim($correo));
        if ($correo === '') { return true; }
        if (isset(self::$cache[$correo])) { return self::$cache[$correo]; }

        $n = (int) BD::valor('SELECT COUNT(*) FROM `cr_supresion` WHERE `correo` = ?', [$correo], 0);
        return self::$cache[$correo] = ($n > 0);
    }

    /** Añade una dirección (si ya estaba, no hace nada). */
    public static function agregar(string $correo, string $motivo = 'manual', string $detalle = ''): bool
    {
        $correo = mb_strtolower(trim($correo));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) { return false; }

        $motivo = in_array($motivo, ['baja', 'rebote', 'queja', 'manual', 'importada'], true) ? $motivo : 'manual';

        BD::ejecutar(
            'INSERT INTO `cr_supresion` (`correo`,`motivo`,`detalle`,`creado`) VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE `motivo` = VALUES(`motivo`), `detalle` = VALUES(`detalle`)',
            [$correo, $motivo, mb_substr($detalle, 0, 400)]
        );
        self::$cache[$correo] = true;

        // Los contactos que ya estén en alguna lista se marcan también.
        $estado = $motivo === 'rebote' ? 'rebotado' : ($motivo === 'baja' ? 'baja' : 'suprimido');
        BD::ejecutar('UPDATE `cr_contactos` SET `estado` = ? WHERE `correo` = ?', [$estado, $correo]);

        return true;
    }

    /** Quita una dirección de la lista (por si se añadió por error). */
    public static function quitar(string $correo): void
    {
        $correo = mb_strtolower(trim($correo));
        BD::ejecutar('DELETE FROM `cr_supresion` WHERE `correo` = ?', [$correo]);
        unset(self::$cache[$correo]);
    }

    /**
     * Importa muchas direcciones de una vez (una por línea, o separadas por comas).
     *
     * @return int Cuántas se añadieron.
     */
    public static function importar(string $texto, string $motivo = 'importada'): int
    {
        $n = 0;
        foreach (preg_split('~[\r\n,;\s]+~', $texto) ?: [] as $linea) {
            $correo = mb_strtolower(trim($linea));
            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) && self::agregar($correo, $motivo)) {
                $n++;
            }
        }
        return $n;
    }

    /** Número total de direcciones suprimidas. */
    public static function total(): int
    {
        return (int) BD::valor('SELECT COUNT(*) FROM `cr_supresion`', [], 0);
    }

    /**
     * Filtra una lista de direcciones dejando solo las que SÍ se pueden usar.
     *
     * @param string[] $correos
     * @return string[]
     */
    public static function filtrar(array $correos): array
    {
        if (!$correos) { return []; }

        $correos = array_values(array_unique(array_map(
            static fn($c) => mb_strtolower(trim((string) $c)),
            $correos
        )));

        $suprimidas = [];
        foreach (array_chunk($correos, 500) as $trozo) {
            $huecos = implode(',', array_fill(0, count($trozo), '?'));
            foreach (BD::todos('SELECT `correo` FROM `cr_supresion` WHERE `correo` IN (' . $huecos . ')', $trozo) as $fila) {
                $suprimidas[$fila['correo']] = true;
            }
        }
        return array_values(array_filter($correos, static fn($c) => !isset($suprimidas[$c])));
    }
}
