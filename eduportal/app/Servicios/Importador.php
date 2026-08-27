<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Core\Database;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Usuario;
use Vendor\Xlsx\Zip;

/** Lectura de XLSX/CSV e importacion masiva de alumnos. */
final class Importador
{
    /** @return array<int,array<int,string>> */
    public static function leer(string $ruta): array
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        return $ext === 'csv' ? self::leerCsv($ruta) : self::leerXlsx($ruta);
    }

    private static function leerCsv(string $ruta): array
    {
        $filas = [];
        $fh = fopen($ruta, 'r');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo abrir el archivo CSV.');
        }
        $primera = true;
        while (($linea = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if ($primera) {
                $primera = false;
                // Quitar BOM
                if (isset($linea[0])) {
                    $linea[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$linea[0]) ?? $linea[0];
                }
            }
            $filas[] = array_map(static fn($c) => trim((string)$c), $linea);
        }
        fclose($fh);
        return $filas;
    }

    private static function leerXlsx(string $ruta): array
    {
        $partes = Zip::leer($ruta);
        $hoja = null;
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet01.xml'] as $c) {
            if (isset($partes[$c])) {
                $hoja = $partes[$c];
                break;
            }
        }
        if ($hoja === null) {
            foreach ($partes as $nombre => $contenido) {
                if (str_starts_with($nombre, 'xl/worksheets/')) {
                    $hoja = $contenido;
                    break;
                }
            }
        }
        if ($hoja === null) {
            throw new \RuntimeException('El archivo no contiene ninguna hoja de calculo.');
        }
        $compartidas = [];
        if (isset($partes['xl/sharedStrings.xml'])) {
            $xml = @simplexml_load_string($partes['xl/sharedStrings.xml']);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $texto = '';
                    if (isset($si->t)) {
                        $texto = (string)$si->t;
                    }
                    foreach ($si->r as $r) {
                        $texto .= (string)$r->t;
                    }
                    $compartidas[] = $texto;
                }
            }
        }
        $xml = @simplexml_load_string($hoja);
        if ($xml === false) {
            throw new \RuntimeException('La hoja de calculo no pudo interpretarse.');
        }
        $filas = [];
        foreach ($xml->sheetData->row as $fila) {
            $actual = [];
            foreach ($fila->c as $celda) {
                $ref = (string)$celda['r'];
                $col = self::indiceColumna(preg_replace('/\d/', '', $ref) ?? 'A');
                $tipo = (string)$celda['t'];
                if ($tipo === 's') {
                    $valor = $compartidas[(int)$celda->v] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = (string)($celda->is->t ?? '');
                } else {
                    $valor = (string)($celda->v ?? '');
                }
                $actual[$col] = trim($valor);
            }
            if ($actual === []) {
                continue;
            }
            $max = max(array_keys($actual));
            $linea = [];
            for ($i = 0; $i <= $max; $i++) {
                $linea[] = $actual[$i] ?? '';
            }
            $filas[] = $linea;
        }
        return $filas;
    }

    private static function indiceColumna(string $letras): int
    {
        $letras = strtoupper($letras);
        $n = 0;
        for ($i = 0, $len = strlen($letras); $i < $len; $i++) {
            $n = $n * 26 + (ord($letras[$i]) - 64);
        }
        return max(0, $n - 1);
    }

    /**
     * @param array<int,array<int,string>> $filas
     * @return array{creados:int, actualizados:int, errores:array<int,string>}
     */
    public static function procesarAlumnos(array $filas, int $seccionId): array
    {
        $creados = 0;
        $actualizados = 0;
        $errores = [];
        if ($filas === []) {
            return ['creados' => 0, 'actualizados' => 0, 'errores' => ['El archivo esta vacio.']];
        }
        $encabezado = array_map(
            static fn($c) => strtolower(trim(preg_replace('/\s+/', '_', (string)$c) ?? '')),
            $filas[0]
        );
        $mapa = array_flip($encabezado);
        if (!isset($mapa['nombres']) || !isset($mapa['apellidos'])) {
            return ['creados' => 0, 'actualizados' => 0, 'errores' => ['El encabezado debe incluir al menos las columnas "nombres" y "apellidos".']];
        }
        $ciclo = Academico::cicloActivoId();
        $get = static function (array $fila, array $mapa, string $clave): string {
            $i = $mapa[$clave] ?? null;
            return $i === null ? '' : trim((string)($fila[$i] ?? ''));
        };

        for ($i = 1, $n = count($filas); $i < $n; $i++) {
            $fila = $filas[$i];
            $nombres = $get($fila, $mapa, 'nombres');
            $apellidos = $get($fila, $mapa, 'apellidos');
            if ($nombres === '' && $apellidos === '') {
                continue;
            }
            if ($nombres === '' || $apellidos === '') {
                $errores[] = 'Fila ' . ($i + 1) . ': nombres y apellidos son obligatorios.';
                continue;
            }
            $codigo = $get($fila, $mapa, 'codigo');
            if ($codigo === '') {
                $codigo = Alumno::siguienteCodigo();
            }
            $fnac = $get($fila, $mapa, 'fecha_nacimiento');
            $fnac = self::normalizarFecha($fnac);
            $genero = strtoupper(substr($get($fila, $mapa, 'genero'), 0, 1));
            if (!in_array($genero, ['M', 'F', 'O'], true)) {
                $genero = null;
            }
            $beca = (float)str_replace('%', '', $get($fila, $mapa, 'beca_pct'));
            $beca = max(0.0, min(100.0, $beca));

            try {
                Database::begin();
                $existente = Database::one('SELECT id FROM alumnos WHERE codigo = :c', ['c' => $codigo]);
                if ($existente) {
                    $alumnoId = (int)$existente['id'];
                    Database::run(
                        'UPDATE alumnos SET nombres = :n, apellidos = :a, dpi = :d, fecha_nacimiento = :f,
                                            genero = :g, direccion = :dir WHERE id = :id',
                        [
                            'n' => mb_substr($nombres, 0, 120), 'a' => mb_substr($apellidos, 0, 120),
                            'd' => mb_substr($get($fila, $mapa, 'dpi'), 0, 30) ?: null,
                            'f' => $fnac, 'g' => $genero,
                            'dir' => mb_substr($get($fila, $mapa, 'direccion'), 0, 255) ?: null,
                            'id' => $alumnoId,
                        ]
                    );
                    $actualizados++;
                } else {
                    $alumnoId = Database::insert(
                        'INSERT INTO alumnos (codigo, nombres, apellidos, dpi, fecha_nacimiento, genero, direccion, estado)
                         VALUES (:c, :n, :a, :d, :f, :g, :dir, \'activo\')',
                        [
                            'c' => mb_substr($codigo, 0, 30), 'n' => mb_substr($nombres, 0, 120),
                            'a' => mb_substr($apellidos, 0, 120),
                            'd' => mb_substr($get($fila, $mapa, 'dpi'), 0, 30) ?: null,
                            'f' => $fnac, 'g' => $genero,
                            'dir' => mb_substr($get($fila, $mapa, 'direccion'), 0, 255) ?: null,
                        ]
                    );
                    $creados++;
                }
                $ins = Database::value('SELECT id FROM inscripciones WHERE alumno_id = :a AND ciclo_id = :c', ['a' => $alumnoId, 'c' => $ciclo]);
                if ($ins) {
                    Database::run('UPDATE inscripciones SET seccion_id = :s, beca_pct = :b WHERE id = :id', ['s' => $seccionId, 'b' => $beca, 'id' => (int)$ins]);
                } else {
                    Database::run(
                        'INSERT INTO inscripciones (alumno_id, ciclo_id, seccion_id, fecha, beca_pct, estado)
                         VALUES (:a, :c, :s, :f, :b, \'activo\')',
                        ['a' => $alumnoId, 'c' => $ciclo, 's' => $seccionId, 'f' => date('Y-m-d'), 'b' => $beca]
                    );
                }
                $encNombre = $get($fila, $mapa, 'encargado');
                if ($encNombre !== '') {
                    $email = $get($fila, $mapa, 'email');
                    $userId = null;
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $u = Usuario::porEmail($email);
                        $userId = $u ? (int)$u['id'] : null;
                    }
                    $existeEnc = Database::value(
                        'SELECT id FROM encargados WHERE alumno_id = :a AND nombre = :n',
                        ['a' => $alumnoId, 'n' => mb_substr($encNombre, 0, 140)]
                    );
                    if (!$existeEnc) {
                        Database::run(
                            'INSERT INTO encargados (alumno_id, user_id, nombre, parentesco, telefono, email, principal, orden)
                             VALUES (:a, :u, :n, :p, :t, :e, 1, 1)',
                            [
                                'a' => $alumnoId, 'u' => $userId,
                                'n' => mb_substr($encNombre, 0, 140),
                                'p' => mb_substr($get($fila, $mapa, 'parentesco'), 0, 40) ?: null,
                                't' => mb_substr($get($fila, $mapa, 'telefono'), 0, 40) ?: null,
                                'e' => $email !== '' ? mb_substr($email, 0, 160) : null,
                            ]
                        );
                    }
                }
                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                $errores[] = 'Fila ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }
        return ['creados' => $creados, 'actualizados' => $actualizados, 'errores' => $errores];
    }

    private static function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (is_numeric($valor) && (float)$valor > 20000) {
            return date('Y-m-d', (int)round(((float)$valor - 25569) * 86400));
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        $ts = strtotime($valor);
        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
