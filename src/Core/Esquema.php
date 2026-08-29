<?php
declare(strict_types=1);

namespace Fel\Core;

use PDO;

/**
 * Aplica un archivo .sql sobre la base de datos.
 *
 * Se separa aqui porque hay dos caminos de instalacion (la linea de comandos y
 * el asistente web) y ambos deben interpretar el archivo igual.
 *
 * Detalle que importa: los archivos de esquema llevan comentarios "--" antes de
 * cada CREATE TABLE. Si uno divide por ";" y descarta los fragmentos que
 * empiezan con "--", se descarta tambien la sentencia que viene detras del
 * comentario y las tablas no se crean. Por eso los comentarios se quitan ANTES
 * de dividir.
 */
final class Esquema
{
    /**
     * Ejecuta todas las sentencias del archivo.
     *
     * @return int Cantidad de sentencias aplicadas.
     * @throws \RuntimeException si el archivo no existe.
     */
    public static function aplicar(PDO $pdo, string $archivo): int
    {
        if (!is_file($archivo)) {
            throw new \RuntimeException("No se encontro el archivo de esquema: {$archivo}");
        }

        $aplicadas = 0;

        foreach (self::sentencias((string) file_get_contents($archivo)) as $sentencia) {
            $pdo->exec($sentencia);
            $aplicadas++;
        }

        return $aplicadas;
    }

    /**
     * Divide el contenido de un .sql en sentencias ejecutables.
     *
     * @return list<string>
     */
    public static function sentencias(string $sql): array
    {
        // Fuera los comentarios de linea, incluidos los que preceden a una sentencia.
        $sinComentarios = preg_replace('/^[ \t]*--.*$/m', '', $sql) ?? $sql;

        $sentencias = [];

        foreach (explode(';', $sinComentarios) as $fragmento) {
            $fragmento = trim($fragmento);

            if ($fragmento !== '') {
                $sentencias[] = $fragmento;
            }
        }

        return $sentencias;
    }
}
