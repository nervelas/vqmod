<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Audit;
use MenuGold\Core\Request;
use MenuGold\Models\Category;
use MenuGold\Models\Product;
use MenuGold\Vendor\Xlsx\XlsxReader;
use MenuGold\Vendor\Xlsx\XlsxWriter;

/**
 * Importacion masiva de platillos desde Excel o CSV.
 */
class Importar extends Base
{
    /** Columnas esperadas, en orden. */
    private const COLUMNAS = [
        'Categoría', 'Nombre', 'Descripción', 'Precio', 'Precio promo',
        'Tiempo (min)', 'Calorías', 'Etiquetas', 'Alérgenos', 'Estación',
        'Agotado', 'Visible', 'Recomendado', 'Código',
    ];

    public function index(): void
    {
        $this->exigir('menu');
        $this->panel('panel/importar', [
            'columnas' => self::COLUMNAS,
            'cats'     => (new Category())->forRestaurant($this->rid)->all('orden ASC'),
            'limites'  => $this->limites(),
            'total'    => (new Product())->forRestaurant($this->rid)->count(),
        ]);
    }

    /** Descarga la plantilla con ejemplos. */
    public function plantilla(): void
    {
        $this->exigir('menu');
        $x = new XlsxWriter();
        $x->setAutor((string)$this->r['nombre']);

        $filas = [self::COLUMNAS];
        $filas[] = ['Entradas', 'Ceviche del chef', 'Corvina fresca, leche de tigre y cilantro criollo',
                    145.00, '', 12, 280, 'nuevo,popular', 'pescado', 'cocina', 'No', 'Sí', 'Sí', 'ENT-01'];
        $filas[] = ['Platos fuertes', 'Lomito Wellington', 'Res premium en hojaldre · 350 g',
                    325.00, 285.00, 32, 890, 'popular', 'gluten,huevo,lacteos', 'cocina', 'No', 'Sí', 'Sí', 'FUE-01'];
        $filas[] = ['Postres', 'Crème brûlée', 'Vainilla de Alta Verapaz',
                    82.00, '', 8, 390, '', 'lacteos,huevo', 'postres', 'No', 'Sí', 'No', 'POS-01'];
        $filas[] = ['Bebidas', 'Café de Antigua', 'Tostado de la semana en prensa francesa',
                    32.00, '', 5, '', 'popular', '', 'bar', 'No', 'Sí', 'No', 'BEB-01'];

        $x->hoja('Platillos', $filas,
            [20, 32, 52, 12, 14, 13, 11, 24, 26, 12, 10, 10, 13, 12],
            ['texto','texto','texto','moneda','moneda','entero','entero','texto','texto','texto','texto','texto','texto','texto']);

        $ayuda = [
            ['Columna', 'Obligatoria', 'Cómo llenarla'],
            ['Categoría', 'Sí', 'Si no existe, se crea automáticamente.'],
            ['Nombre', 'Sí', 'Nombre del platillo tal como aparecerá en el menú.'],
            ['Descripción', 'No', 'Texto apetitoso. Máximo 900 caracteres.'],
            ['Precio', 'Sí', 'Solo números. Ej. 145.00'],
            ['Precio promo', 'No', 'Precio rebajado. Debe ser menor al precio normal.'],
            ['Tiempo (min)', 'No', 'Minutos de preparación. Por defecto 15.'],
            ['Calorías', 'No', 'Solo números.'],
            ['Etiquetas', 'No', 'Separadas por coma: ' . implode(', ', array_keys(Product::ETIQUETAS))],
            ['Alérgenos', 'No', 'Separados por coma: ' . implode(', ', Product::ALERGENOS)],
            ['Estación', 'No', 'cocina, bar o postres. Por defecto cocina.'],
            ['Agotado', 'No', 'Sí / No'],
            ['Visible', 'No', 'Sí / No. Por defecto Sí.'],
            ['Recomendado', 'No', 'Sí / No'],
            ['Código', 'No', 'Tu código interno. Si coincide con uno existente, se actualiza ese platillo.'],
        ];
        $x->hoja('Instrucciones', $ayuda, [18, 14, 76], ['texto', 'texto', 'texto']);

        $this->download($x->output(), 'plantilla-menugold.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function procesar(): void
    {
        $this->exigir('menu');
        $archivo = Request::file('archivo');
        if (!$archivo) {
            flash('error', 'Elige el archivo Excel o CSV que quieres importar.');
            redirect('panel/importar');
        }
        $ext = strtolower((string)pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            flash('error', 'Formato no válido. Usa un archivo .xlsx o .csv.');
            redirect('panel/importar');
        }
        if (($archivo['size'] ?? 0) > 8388608) {
            flash('error', 'El archivo supera los 8 MB.');
            redirect('panel/importar');
        }

        $tmp = MG_ROOT . '/storage/tmp/import-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!is_dir(dirname($tmp))) @mkdir(dirname($tmp), 0750, true);
        if (!@move_uploaded_file((string)$archivo['tmp_name'], $tmp) && !@copy((string)$archivo['tmp_name'], $tmp)) {
            flash('error', 'No se pudo leer el archivo subido.');
            redirect('panel/importar');
        }

        // Comprobamos que el contenido sea de verdad lo que dice la extension:
        // un .xlsx empieza siempre por la firma de un ZIP, y un .csv debe ser texto.
        $cabecera = (string)file_get_contents($tmp, false, null, 0, 8);
        $contenidoOk = $ext === 'xlsx'
            ? strncmp($cabecera, "PK\x03\x04", 4) === 0
            : strpos($cabecera, "\0") === false;
        if (!$contenidoOk) {
            unlink($tmp);
            flash('error', 'El archivo no es un Excel o CSV válido.');
            redirect('panel/importar');
        }

        $lector = new XlsxReader();
        $filas = $lector->leer($tmp, 3000);
        unlink($tmp);

        if ($lector->error !== '') {
            flash('error', $lector->error);
            redirect('panel/importar');
        }
        if (count($filas) < 2) {
            flash('error', 'El archivo no tiene filas de datos. Descarga la plantilla y úsala como base.');
            redirect('panel/importar');
        }

        $simular = Request::bool('simular');
        $resultado = $this->importar($filas, $simular);

        if ($simular) {
            $_SESSION['_import_previa'] = $resultado;
            flash('info', 'Vista previa: ' . $resultado['nuevos'] . ' nuevo(s), '
                . $resultado['actualizados'] . ' actualizado(s), ' . count($resultado['errores']) . ' con problemas.');
        } else {
            Audit::log('importacion', 'products', 0, null, [
                'nuevos' => $resultado['nuevos'], 'actualizados' => $resultado['actualizados'],
            ]);
            $_SESSION['_import_previa'] = $resultado;
            flash('exito', 'Importación lista: ' . $resultado['nuevos'] . ' platillo(s) creados y '
                . $resultado['actualizados'] . ' actualizados.');
        }
        redirect('panel/importar');
    }

    /**
     * @param array<int,array<int,string>> $filas
     * @return array{nuevos:int,actualizados:int,errores:array,previa:array}
     */
    private function importar(array $filas, bool $simular): array
    {
        $cm = (new Category())->forRestaurant($this->rid);
        $pm = (new Product())->forRestaurant($this->rid);

        // Mapa de categorías existentes (por nombre en minúsculas)
        $cats = [];
        foreach ($cm->all('orden ASC') as $c) $cats[mb_strtolower(trim((string)$c['nombre']))] = (int)$c['id'];

        $lim = (int)$this->limites()['max_productos'];
        $usados = $pm->count();

        $nuevos = 0; $actualizados = 0; $errores = []; $previa = [];
        $orden = $pm->maxOrder();

        // Salta la cabecera si la primera celda no parece un dato
        $inicio = 0;
        $primera = mb_strtolower(trim((string)($filas[0][0] ?? '')));
        if ($primera === '' || in_array($primera, ['categoría', 'categoria', 'category'], true)) $inicio = 1;

        for ($i = $inicio; $i < count($filas); $i++) {
            $f = $filas[$i];
            $n = $i + 1;
            $nombre = trim((string)($f[1] ?? ''));
            if ($nombre === '' && trim((string)($f[0] ?? '')) === '') continue;   // fila vacía
            if ($nombre === '') { $errores[] = "Fila {$n}: falta el nombre del platillo."; continue; }

            $precio = $this->numero((string)($f[3] ?? ''));
            if ($precio === null) { $errores[] = "Fila {$n}: el precio de «{$nombre}» no es un número válido."; continue; }

            $catNombre = trim((string)($f[0] ?? ''));
            $catId = null;
            if ($catNombre !== '') {
                $clave = mb_strtolower($catNombre);
                if (isset($cats[$clave])) {
                    $catId = $cats[$clave];
                } elseif (!$simular) {
                    $catId = $cm->create(['nombre' => mb_substr($catNombre, 0, 120), 'orden' => count($cats), 'activo' => 1]);
                    $cats[$clave] = $catId;
                }
            }

            $promo = $this->numero((string)($f[4] ?? ''));
            if ($promo !== null && $promo >= $precio) $promo = null;

            $etiquetas = $this->lista((string)($f[7] ?? ''), array_keys(Product::ETIQUETAS));
            $alergenos = $this->lista((string)($f[8] ?? ''), Product::ALERGENOS);
            $estacion = mb_strtolower(trim((string)($f[9] ?? 'cocina')));
            if (!in_array($estacion, ['cocina', 'bar', 'postres'], true)) $estacion = 'cocina';

            $sku = trim((string)($f[13] ?? ''));
            $existente = null;
            if ($sku !== '') $existente = $pm->first('sku = :s', ['s' => $sku]);
            if (!$existente) $existente = $pm->first('nombre = :n', ['n' => $nombre]);

            if (!$existente && $lim > 0 && $usados >= $lim) {
                $errores[] = "Fila {$n}: «{$nombre}» no se importó porque alcanzaste el límite de {$lim} platillos de tu plan.";
                continue;
            }

            $datos = [
                'category_id'  => $catId,
                'nombre'       => mb_substr($nombre, 0, 160),
                'descripcion'  => mb_substr(trim((string)($f[2] ?? '')), 0, 900),
                'precio'       => $precio,
                'precio_promo' => $promo,
                'tiempo_prep'  => max(0, min(240, (int)$this->numero((string)($f[5] ?? '15'), 15))),
                'calorias'     => $this->numero((string)($f[6] ?? '')) !== null ? (int)$this->numero((string)($f[6] ?? '')) : null,
                'etiquetas'    => implode(',', $etiquetas),
                'alergenos'    => implode(',', $alergenos),
                'estacion'     => $estacion,
                'agotado'      => $this->siNo((string)($f[10] ?? 'No')) ? 1 : 0,
                'activo'       => $this->siNo((string)($f[11] ?? 'Sí'), true) ? 1 : 0,
                'destacado'    => $this->siNo((string)($f[12] ?? 'No')) ? 1 : 0,
                'sku'          => mb_substr($sku, 0, 40),
            ];

            $previa[] = [
                'fila'      => $n,
                'nombre'    => $datos['nombre'],
                'categoria' => $catNombre,
                'precio'    => $precio,
                'accion'    => $existente ? 'actualizar' : 'crear',
            ];

            if (!$simular) {
                if ($existente) {
                    $pm->updateById((int)$existente['id'], $datos);
                } else {
                    $datos['orden'] = ++$orden;
                    $pm->create($datos);
                    $usados++;
                }
            }
            if ($existente) $actualizados++; else $nuevos++;
        }

        return [
            'nuevos'       => $nuevos,
            'actualizados' => $actualizados,
            'errores'      => array_slice($errores, 0, 40),
            'previa'       => array_slice($previa, 0, 200),
            'simulacion'   => $simular,
        ];
    }

    private function numero(string $v, ?float $default = null): ?float
    {
        $v = trim(str_replace([' ', 'Q', '$', ','], '', $v));
        if ($v === '') return $default;
        // Admite coma decimal
        if (substr_count($v, '.') === 0 && substr_count($v, ',') === 1) $v = str_replace(',', '.', $v);
        return is_numeric($v) ? round((float)$v, 2) : $default;
    }

    private function siNo(string $v, bool $default = false): bool
    {
        $v = mb_strtolower(trim($v));
        if ($v === '') return $default;
        return in_array($v, ['si', 'sí', 'yes', '1', 'x', 'true', 'verdadero'], true);
    }

    private function lista(string $v, array $permitidos): array
    {
        $partes = array_map(static function ($x) {
            $x = mb_strtolower(trim($x));
            return str_replace([' ', 'á', 'é', 'í', 'ó', 'ú'], ['_', 'a', 'e', 'i', 'o', 'u'], $x);
        }, explode(',', $v));
        return array_values(array_intersect(array_filter($partes), $permitidos));
    }
}
