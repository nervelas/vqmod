<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Sheet;
use App\Core\Uploader;
use App\Core\Xlsx;
use App\Models\AttributeDef;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;

/**
 * Importador de catálogo desde el CSV exportado de WooCommerce
 * (o desde la plantilla propia), con vista previa y reporte de errores.
 */
final class ImportController extends Controller
{
    /** Campos destino y las cabeceras de WooCommerce que se reconocen solas. */
    public const FIELDS = [
        'code'        => ['label' => 'Código / SKU',        'auto' => ['sku', 'código', 'codigo', 'clave', 'referencia']],
        'name'        => ['label' => 'Nombre',              'auto' => ['name', 'nombre', 'título', 'titulo', 'producto']],
        'short_desc'  => ['label' => 'Descripción corta',   'auto' => ['short description', 'descripción corta', 'descripcion corta', 'resumen']],
        'description' => ['label' => 'Descripción larga',   'auto' => ['description', 'descripción', 'descripcion', 'detalle']],
        'price'       => ['label' => 'Precio',              'auto' => ['regular price', 'precio normal', 'precio', 'price']],
        'category'    => ['label' => 'Categoría',           'auto' => ['categories', 'categorías', 'categorias', 'categoría', 'categoria']],
        'brand'       => ['label' => 'Marca',               'auto' => ['brand', 'marca', 'fabricante']],
        'unit'        => ['label' => 'Unidad',              'auto' => ['unit', 'unidad', 'medida']],
        'stock_note'  => ['label' => 'Nota de existencia',  'auto' => ['stock status', 'inventario', 'existencia']],
        'image'       => ['label' => 'URL de imagen',       'auto' => ['images', 'imagen', 'imágenes', 'imagenes', 'image']],
        'active'      => ['label' => 'Activo (1/0)',        'auto' => ['published', 'publicado', 'activo', 'estado']],
        'featured'    => ['label' => 'Destacado (1/0)',     'auto' => ['is featured?', 'destacado', 'featured']],
        'attr1_name'  => ['label' => 'Atributo 1 · nombre', 'auto' => ['attribute 1 name', 'atributo 1 nombre']],
        'attr1_value' => ['label' => 'Atributo 1 · valor',  'auto' => ['attribute 1 value(s)', 'atributo 1 valor', 'attribute 1 value']],
        'attr2_name'  => ['label' => 'Atributo 2 · nombre', 'auto' => ['attribute 2 name', 'atributo 2 nombre']],
        'attr2_value' => ['label' => 'Atributo 2 · valor',  'auto' => ['attribute 2 value(s)', 'atributo 2 valor', 'attribute 2 value']],
        'attr3_name'  => ['label' => 'Atributo 3 · nombre', 'auto' => ['attribute 3 name', 'atributo 3 nombre']],
        'attr3_value' => ['label' => 'Atributo 3 · valor',  'auto' => ['attribute 3 value(s)', 'atributo 3 valor', 'attribute 3 value']],
    ];

    public function index(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->view('panel/import', [
            'title'   => 'Importar catálogo',
            'fields'  => self::FIELDS,
            'history' => DB::all('SELECT i.*, u.name AS user_name FROM imports i LEFT JOIN users u ON u.id = i.user_id ORDER BY i.id DESC LIMIT 10'),
            'step'    => 1,
        ], 'layout/panel');
    }

    /** Paso 2: sube el archivo, muestra vista previa y propone el mapeo. */
    public function analyze(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $files = Uploader::files('file');
        if (!$files) {
            Flash::error('Seleccione un archivo CSV o XLSX.');
            redirect('/panel/importar');
        }
        $path = Uploader::sheet($files[0]);
        if (!$path) {
            Flash::error('El archivo no es un CSV/XLSX válido o excede 20 MB.');
            redirect('/panel/importar');
        }
        $rows = Sheet::read($path, 31);
        if (count($rows) < 2) {
            @unlink($path);
            Flash::error('El archivo no tiene filas de datos.');
            redirect('/panel/importar');
        }
        $headers = $rows[0];
        $map = [];
        foreach (self::FIELDS as $key => $def) {
            $map[$key] = '';
            foreach ($headers as $i => $h) {
                $hn = mb_strtolower(trim((string) $h));
                if (in_array($hn, $def['auto'], true)) {
                    $map[$key] = (string) $i;
                    break;
                }
            }
        }
        App::startSession();
        $_SESSION['import_file'] = $path;
        $_SESSION['import_name'] = mb_substr((string) $files[0]['name'], 0, 180);

        $this->view('panel/import', [
            'title'    => 'Importar catálogo',
            'fields'   => self::FIELDS,
            'headers'  => $headers,
            'preview'  => array_slice($rows, 1, 12),
            'map'      => $map,
            'rowCount' => max(0, count(Sheet::read($path)) - 1),
            'fileName' => $_SESSION['import_name'],
            'history'  => [],
            'step'     => 2,
        ], 'layout/panel');
    }

    /** Paso 3: ejecuta la importación y devuelve el reporte. */
    public function run(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        App::startSession();
        $path = (string) ($_SESSION['import_file'] ?? '');
        $name = (string) ($_SESSION['import_name'] ?? 'archivo.csv');
        if ($path === '' || !is_file($path)) {
            Flash::error('La sesión de importación expiró. Vuelva a subir el archivo.');
            redirect('/panel/importar');
        }
        $map = [];
        foreach (Request::arr('map') as $k => $v) {
            if (isset(self::FIELDS[$k]) && $v !== '') {
                $map[$k] = (int) $v;
            }
        }
        if (!isset($map['name'])) {
            Flash::error('Debe mapear al menos la columna del Nombre.');
            redirect('/panel/importar');
        }
        $updateExisting = Request::bool('update_existing');
        $defaultActive  = Request::bool('default_active');

        $rows = Sheet::read($path);
        array_shift($rows);
        $ok = 0;
        $err = 0;
        $errors = [];

        foreach ($rows as $n => $row) {
            $line = $n + 2;
            $get = static function (string $k) use ($map, $row): string {
                return isset($map[$k]) ? trim((string) ($row[$map[$k]] ?? '')) : '';
            };
            $pname = mb_substr($get('name'), 0, 200);
            if ($pname === '') {
                continue; // fila vacía
            }
            $code = mb_substr($get('code'), 0, 55);
            try {
                $existing = $code !== '' ? Product::byCode($code) : null;
                if ($existing && !$updateExisting) {
                    $err++;
                    $errors[] = ['fila' => $line, 'motivo' => 'El código ' . $code . ' ya existe (no se actualizó)'];
                    continue;
                }
                $catId = null;
                $catRaw = $get('category');
                if ($catRaw !== '') {
                    // WooCommerce usa "Padre > Hijo" y separa categorías por coma.
                    $first = trim(explode(',', $catRaw)[0]);
                    $parts = array_map('trim', explode('>', $first));
                    $parentId = null;
                    foreach ($parts as $part) {
                        if ($part === '') {
                            continue;
                        }
                        $catId = self::ensureCategory($part, $parentId);
                        $parentId = $catId;
                    }
                }
                $brandId = $get('brand') !== '' ? Brand::findOrCreate($get('brand')) : null;
                $priceRaw = str_replace([',', ' ', 'Q', '$'], '', $get('price'));
                $active = $get('active') !== '' ? self::truthy($get('active')) : ($defaultActive ? 1 : 0);

                $data = [
                    'category_id' => $catId,
                    'brand_id'    => $brandId,
                    'name'        => $pname,
                    'short_desc'  => mb_substr(strip_tags($get('short_desc')), 0, 300) ?: null,
                    'description' => mb_substr(strip_tags($get('description')), 0, 12000) ?: null,
                    'price'       => is_numeric($priceRaw) ? max(0, (float) $priceRaw) : 0,
                    'unit'        => mb_substr($get('unit') ?: 'unidad', 0, 20),
                    'stock_note'  => mb_substr($get('stock_note'), 0, 60) ?: null,
                    'featured'    => $get('featured') !== '' ? self::truthy($get('featured')) : 0,
                    'active'      => $active,
                    'updated_at'  => nowSql(),
                ];
                if ($existing) {
                    $pid = (int) $existing['id'];
                    DB::update('products', $data, 'id = :id', ['id' => $pid]);
                } else {
                    $data['code']       = Product::uniqueCode($code !== '' ? $code : strtoupper(substr(slugify($pname), 0, 10)) . '-' . random_int(100, 999));
                    $data['slug']       = Product::uniqueSlug($pname);
                    $data['created_at'] = nowSql();
                    $pid = DB::insert('products', $data);
                }
                // Atributos técnicos (hasta 3 pares nombre/valor).
                for ($a = 1; $a <= 3; $a++) {
                    $an = $get('attr' . $a . '_name');
                    $av = $get('attr' . $a . '_value');
                    if ($an === '' || $av === '') {
                        continue;
                    }
                    $aid = self::ensureAttribute($an, $catId);
                    $av = mb_substr(trim(explode(',', $av)[0]), 0, 190);
                    DB::run(
                        'INSERT INTO product_attributes (product_id, attribute_id, value) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE value = VALUES(value)',
                        [$pid, $aid, $av]
                    );
                }
                $ok++;
            } catch (\Throwable $e) {
                $err++;
                $errors[] = ['fila' => $line, 'motivo' => mb_substr($e->getMessage(), 0, 160)];
                \App\Core\ErrorHandler::log('Import fila ' . $line . ': ' . $e->getMessage());
            }
            if (count($errors) > 300) {
                $errors[] = ['fila' => 0, 'motivo' => 'Se omitieron más errores para no saturar el reporte.'];
                break;
            }
        }

        $importId = DB::insert('imports', [
            'user_id'    => (int) $u['id'],
            'type'       => 'productos',
            'filename'   => $name,
            'rows_total' => count($rows),
            'rows_ok'    => $ok,
            'rows_error' => $err,
            'report'     => json_encode(array_slice($errors, 0, 300), JSON_UNESCAPED_UNICODE),
            'created_at' => nowSql(),
        ]);
        Audit::log('catalogo.importar', 'import', $importId, ['ok' => $ok, 'error' => $err, 'archivo' => $name]);
        @unlink($path);
        unset($_SESSION['import_file'], $_SESSION['import_name']);

        $this->view('panel/import', [
            'title'   => 'Importación finalizada',
            'fields'  => self::FIELDS,
            'result'  => ['ok' => $ok, 'err' => $err, 'total' => count($rows), 'errors' => $errors, 'file' => $name],
            'history' => DB::all('SELECT i.*, u.name AS user_name FROM imports i LEFT JOIN users u ON u.id = i.user_id ORDER BY i.id DESC LIMIT 10'),
            'step'    => 3,
        ], 'layout/panel');
    }

    private static function truthy(string $v): int
    {
        $v = mb_strtolower(trim($v));
        return in_array($v, ['1', 'si', 'sí', 'yes', 'true', 'publicado', 'published', 'instock', 'in stock', 'activo'], true) ? 1 : 0;
    }

    private static function ensureCategory(string $name, ?int $parentId): int
    {
        $slug = Category::uniqueSlug($name);
        $row = DB::one('SELECT id FROM categories WHERE name = ? AND (parent_id <=> ?) LIMIT 1', [mb_substr($name, 0, 140), $parentId]);
        if ($row) {
            return (int) $row['id'];
        }
        return DB::insert('categories', [
            'parent_id'  => $parentId,
            'name'       => mb_substr($name, 0, 140),
            'slug'       => $slug,
            'active'     => 1,
            'sort'       => (int) DB::value('SELECT COALESCE(MAX(sort),0)+1 FROM categories', [], 1),
            'created_at' => nowSql(),
        ]);
    }

    private static function ensureAttribute(string $label, ?int $catId): int
    {
        $code = mb_substr(slugify($label, '_'), 0, 50);
        $row = DB::one('SELECT id FROM attribute_defs WHERE code = ? LIMIT 1', [$code]);
        if ($row) {
            return (int) $row['id'];
        }
        return DB::insert('attribute_defs', [
            'category_id' => null,
            'code'        => $code,
            'label'       => mb_substr($label, 0, 90),
            'type'        => 'texto',
            'filterable'  => 1,
            'sort'        => (int) DB::value('SELECT COALESCE(MAX(sort),0)+1 FROM attribute_defs', [], 1),
        ]);
    }

    public function template(array $params = []): void
    {
        $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $head = ['SKU', 'Name', 'Short description', 'Description', 'Regular price', 'Categories', 'Brand', 'Unit', 'Stock status', 'Published', 'Is featured?', 'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 2 name', 'Attribute 2 value(s)'];
        $rows = [
            $head,
            ['SM-T21-25', 'Sello mecánico Tipo 21 · 25 mm', 'Sello de fuelle para bombas centrífugas', 'Sello mecánico de fuelle de hule con caras carbón/cerámica.', '385.00', 'Sellos mecánicos > Tipo 21', 'Chesterton', 'unidad', 'instock', '1', '1', 'Material', 'Carbón / Cerámica', 'Medida', '25 mm'],
            ['EMP-TFL-3', 'Empaque de teflón 3 mm', 'Lámina de PTFE virgen', 'Lámina de teflón virgen para bridas y tapas.', '145.50', 'Empaques > Teflón', 'Garlock', 'metro', 'instock', '1', '0', 'Espesor', '3 mm', 'Temperatura máx.', '260 °C'],
        ];
        (new \App\Core\Xlsx())->addSheet('Productos', $rows, [16, 40, 40, 46, 14, 30, 16, 12, 14, 12, 12, 18, 22, 18, 22])
            ->download('plantilla-catalogo-cotizapro.xlsx');
    }

    public function templateCustomers(array $params = []): void
    {
        $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $rows = [
            ['Nombre', 'Razón social', 'NIT', 'Correo', 'Teléfono', 'Ciudad', 'Sector'],
            ['Ingenio San Rafael', 'Ingenio San Rafael, S.A.', '1234567-8', 'compras@sanrafael.gt', '5555-1122', 'Escuintla', 'Azucarero'],
        ];
        (new Xlsx())->addSheet('Clientes', $rows, [32, 34, 14, 28, 16, 16, 18])->download('plantilla-clientes-cotizapro.xlsx');
    }
}
