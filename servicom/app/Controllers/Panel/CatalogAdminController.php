<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Uploader;
use App\Models\AttributeDef;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;

final class CatalogAdminController extends Controller
{
    // ------------------------------------------------------------ productos
    public function products(array $params = []): void
    {
        [$u, $c] = $this->panel();
        [$page, $per, $offset] = Request::page(25);
        $active = Request::str('estado');
        [$rows, $total] = Product::search([
            'q'           => Request::str('q'),
            'category_id' => Request::int('categoria'),
            'brand_id'    => Request::int('marca'),
            'active'      => $active === '' ? '' : (int) $active,
            'sort'        => Request::str('orden') ?: 'nombre',
            'limit'       => $per,
            'offset'      => $offset,
        ]);
        $this->view('panel/products', [
            'title'      => 'Catálogo de productos',
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'pages'      => (int) ceil($total / $per),
            'categories' => Category::options(),
            'brands'     => Brand::all(),
        ], 'layout/panel');
    }

    public function productForm(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $id  = (int) ($params['id'] ?? 0);
        $p   = $id ? Product::find($id) : null;
        if ($id && !$p) {
            ErrorHandler::render(404);
        }

        if (Request::isPost()) {
            Csrf::verify();
            $name = mb_substr(Request::str('name'), 0, 200);
            if ($name === '') {
                Flash::error('El nombre del producto es obligatorio.');
                Flash::keep($_POST);
                redirect($id ? '/panel/productos/' . $id : '/panel/productos/nuevo');
            }
            $catId   = Request::int('category_id') ?: null;
            $brandId = Request::int('brand_id') ?: null;
            if ($catId && !Category::find($catId)) {
                $catId = null;
            }
            if ($brandId && !Brand::find($brandId)) {
                $brandId = null;
            }
            $vis = Request::str('price_visibility');
            if (!in_array($vis, ['heredar', 'publico', 'clientes', 'oculto'], true)) {
                $vis = 'heredar';
            }
            $code = Request::str('code') ?: strtoupper(substr(slugify($name), 0, 12)) . '-' . random_int(100, 999);
            $data = [
                'category_id'      => $catId,
                'brand_id'         => $brandId,
                'code'             => Product::uniqueCode($code, $id ?: null),
                'name'             => $name,
                'slug'             => Product::uniqueSlug(Request::str('slug') ?: $name, $id ?: null),
                'short_desc'       => mb_substr(Request::str('short_desc'), 0, 300) ?: null,
                'description'      => mb_substr(Request::str('description'), 0, 12000) ?: null,
                'application'      => mb_substr(Request::str('application'), 0, 255) ?: null,
                'unit'             => mb_substr(Request::str('unit') ?: 'unidad', 0, 20),
                'price'            => max(0, Request::float('price')),
                'cost'             => max(0, Request::float('cost')),
                'price_visibility' => $vis,
                'min_qty'          => max(0.01, Request::float('min_qty', 1)),
                'lead_time'        => mb_substr(Request::str('lead_time'), 0, 60) ?: null,
                'stock_note'       => mb_substr(Request::str('stock_note'), 0, 60) ?: null,
                'featured'         => Request::bool('featured') ? 1 : 0,
                'active'           => Request::bool('active') ? 1 : 0,
                'seo_title'        => mb_substr(Request::str('seo_title'), 0, 190) ?: null,
                'seo_description'  => mb_substr(Request::str('seo_description'), 0, 300) ?: null,
                'updated_at'       => nowSql(),
            ];
            if ($id) {
                $oldPrice = (float) $p['price'];
                DB::update('products', $data, 'id = :id', ['id' => $id]);
                if (abs($oldPrice - (float) $data['price']) > 0.001) {
                    Audit::log('producto.precio', 'product', $id, ['de' => $oldPrice, 'a' => $data['price'], 'code' => $data['code']]);
                }
                Audit::log('producto.editar', 'product', $id, ['code' => $data['code']]);
            } else {
                $data['created_at'] = nowSql();
                $id = DB::insert('products', $data);
                Audit::log('producto.crear', 'product', $id, ['code' => $data['code']]);
            }

            // Atributos técnicos.
            DB::delete('product_attributes', 'product_id = :p', ['p' => $id]);
            foreach (Request::arr('attr') as $aid => $val) {
                $aid = (int) $aid;
                $val = is_string($val) ? mb_substr(trim($val), 0, 190) : '';
                if ($val === '' || !AttributeDef::find($aid)) {
                    continue;
                }
                DB::insert('product_attributes', ['product_id' => $id, 'attribute_id' => $aid, 'value' => $val]);
            }

            // Precios por lista de clientes.
            foreach (Request::arr('plist') as $plid => $val) {
                $plid = (int) $plid;
                $val  = (float) str_replace(',', '', (string) $val);
                if (!DB::one('SELECT id FROM price_lists WHERE id = ? LIMIT 1', [$plid])) {
                    continue;
                }
                if ($val > 0) {
                    DB::run('INSERT INTO product_prices (product_id, price_list_id, price) VALUES (?,?,?)
                             ON DUPLICATE KEY UPDATE price = VALUES(price)', [$id, $plid, $val]);
                } else {
                    DB::delete('product_prices', 'product_id = :p AND price_list_id = :l', ['p' => $id, 'l' => $plid]);
                }
            }

            // Imágenes (recomprimidas a WebP + JPG).
            $sort = (int) DB::value('SELECT COALESCE(MAX(sort),0) FROM product_images WHERE product_id = ?', [$id], 0);
            $bad = 0;
            foreach (Uploader::files('images') as $f) {
                $res = Uploader::image($f, 'productos');
                if (!$res) {
                    $bad++;
                    continue;
                }
                DB::insert('product_images', [
                    'product_id' => $id,
                    'path'       => $res['path'],
                    'path_webp'  => $res['path_webp'],
                    'path_thumb' => $res['path_thumb'],
                    'width'      => $res['width'],
                    'height'     => $res['height'],
                    'blur'       => $res['blur'],
                    'alt'        => $res['alt'] ?: $name,
                    'sort'       => ++$sort,
                ]);
            }
            // Documentos PDF (fichas técnicas).
            foreach (Uploader::files('documents') as $f) {
                $res = Uploader::pdf($f, 'documentos');
                if (!$res) {
                    $bad++;
                    continue;
                }
                DB::insert('product_documents', [
                    'product_id' => $id,
                    'name'       => $res['name'],
                    'path'       => $res['path'],
                    'size'       => $res['size'],
                    'created_at' => nowSql(),
                ]);
            }
            if ($bad > 0) {
                Flash::warn("Se rechazaron {$bad} archivo(s) por tipo o tamaño no permitido.");
            }
            Flash::ok('Producto guardado.');
            redirect('/panel/productos/' . $id);
        }

        $this->view('panel/product-form', [
            'title'      => $p ? 'Editar producto' : 'Nuevo producto',
            'p'          => $p,
            'categories' => Category::options(),
            'brands'     => Brand::all(),
            'attrs'      => AttributeDef::forCategory($p ? (int) $p['category_id'] : null),
            'allAttrs'   => AttributeDef::all(),
            'values'     => $p ? array_column(DB::all('SELECT attribute_id, value FROM product_attributes WHERE product_id = ?', [(int) $p['id']]), 'value', 'attribute_id') : [],
            'images'     => $p ? Product::images((int) $p['id']) : [],
            'documents'  => $p ? Product::documents((int) $p['id']) : [],
            'priceLists' => DB::all('SELECT * FROM price_lists ORDER BY name'),
            'listPrices' => $p ? array_column(DB::all('SELECT price_list_id, price FROM product_prices WHERE product_id = ?', [(int) $p['id']]), 'price', 'price_list_id') : [],
        ], 'layout/panel');
    }

    public function productDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $id = (int) $params['id'];
        $p = Product::find($id);
        if (!$p) {
            ErrorHandler::render(404);
        }
        foreach (Product::images($id) as $img) {
            Uploader::deleteImage($img);
        }
        DB::delete('product_images', 'product_id = :p', ['p' => $id]);
        DB::delete('product_documents', 'product_id = :p', ['p' => $id]);
        DB::delete('product_attributes', 'product_id = :p', ['p' => $id]);
        DB::delete('product_prices', 'product_id = :p', ['p' => $id]);
        DB::delete('products', 'id = :id', ['id' => $id]);
        Audit::log('producto.eliminar', 'product', $id, ['code' => $p['code']]);
        Flash::ok('Producto eliminado.');
        redirect('/panel/productos');
    }

    public function productDuplicate(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $p = Product::find((int) $params['id']);
        if (!$p) {
            ErrorHandler::render(404);
        }
        $new = $p;
        unset($new['id']);
        $new['name']       = mb_substr($p['name'] . ' (copia)', 0, 200);
        $new['code']       = Product::uniqueCode($p['code'] . '-C');
        $new['slug']       = Product::uniqueSlug($new['name']);
        $new['active']     = 0;
        $new['views']      = 0;
        $new['quote_count'] = 0;
        $new['created_at'] = nowSql();
        $new['updated_at'] = nowSql();
        $newId = DB::insert('products', $new);
        foreach (DB::all('SELECT * FROM product_attributes WHERE product_id = ?', [(int) $p['id']]) as $a) {
            DB::insert('product_attributes', ['product_id' => $newId, 'attribute_id' => (int) $a['attribute_id'], 'value' => (string) $a['value']]);
        }
        Audit::log('producto.duplicar', 'product', $newId, ['origen' => (int) $p['id']]);
        Flash::ok('Producto duplicado. Está inactivo hasta que lo revise.');
        redirect('/panel/productos/' . $newId);
    }

    public function imageDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $img = DB::one('SELECT * FROM product_images WHERE id = ? LIMIT 1', [(int) $params['id']]);
        if ($img) {
            Uploader::deleteImage($img);
            DB::delete('product_images', 'id = :id', ['id' => (int) $img['id']]);
        }
        $this->back('/panel/productos');
    }

    public function documentDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        $this->guardPost();
        $d = DB::one('SELECT * FROM product_documents WHERE id = ? LIMIT 1', [(int) $params['id']]);
        if ($d) {
            $abs = STORAGE_PATH . '/uploads/' . $d['path'];
            $root = realpath(STORAGE_PATH . '/uploads');
            if (is_file($abs) && $root && str_starts_with((string) realpath($abs), $root)) {
                @unlink($abs);
            }
            DB::delete('product_documents', 'id = :id', ['id' => (int) $d['id']]);
        }
        $this->back('/panel/productos');
    }

    // ----------------------------------------------------------- categorías
    public function categories(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $name = mb_substr(Request::str('name'), 0, 140);
            if ($name === '') {
                Flash::error('Escriba el nombre de la categoría.');
                redirect('/panel/categorias');
            }
            $parent = Request::int('parent_id') ?: null;
            if ($parent && (!Category::find($parent) || $parent === $id)) {
                $parent = null;
            }
            $data = [
                'parent_id'   => $parent,
                'name'        => $name,
                'slug'        => Category::uniqueSlug(Request::str('slug') ?: $name, $id ?: null),
                'code'        => mb_substr(Request::str('code'), 0, 20) ?: null,
                'description' => mb_substr(Request::str('description'), 0, 3000) ?: null,
                'active'      => Request::bool('active') ? 1 : 0,
                'seo_title'   => mb_substr(Request::str('seo_title'), 0, 190) ?: null,
                'seo_description' => mb_substr(Request::str('seo_description'), 0, 300) ?: null,
            ];
            $f = Uploader::files('image');
            if ($f) {
                $res = Uploader::image($f[0], 'categorias', 1200, 900);
                if ($res) {
                    $data['image'] = $res['path_webp'] ?: $res['path'];
                }
            }
            if ($id && Category::find($id)) {
                DB::update('categories', $data, 'id = :id', ['id' => $id]);
                Audit::log('categoria.editar', 'category', $id, ['nombre' => $name]);
            } else {
                $data['created_at'] = nowSql();
                $data['sort'] = (int) DB::value('SELECT COALESCE(MAX(sort),0)+1 FROM categories', [], 1);
                $id = DB::insert('categories', $data);
                Audit::log('categoria.crear', 'category', $id, ['nombre' => $name]);
            }
            Flash::ok('Categoría guardada.');
            redirect('/panel/categorias');
        }
        $this->view('panel/categories', [
            'title' => 'Categorías',
            'tree'  => Category::tree(),
            'options' => Category::options(),
            'edit'  => Request::int('editar') ? Category::find(Request::int('editar')) : null,
        ], 'layout/panel');
    }

    public function categoryOrder(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        Csrf::verify();
        $order = Request::arr('order');
        $i = 0;
        foreach ($order as $entry) {
            $id     = (int) ($entry['id'] ?? 0);
            $parent = (int) ($entry['parent'] ?? 0) ?: null;
            if (!$id || !Category::find($id)) {
                continue;
            }
            if ($parent && (!Category::find($parent) || $parent === $id)) {
                $parent = null;
            }
            DB::update('categories', ['sort' => $i++, 'parent_id' => $parent], 'id = :id', ['id' => $id]);
        }
        jsonOut(['ok' => true]);
    }

    public function categoryDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        $id = (int) $params['id'];
        if (!Category::find($id)) {
            ErrorHandler::render(404);
        }
        DB::run('UPDATE products SET category_id = NULL WHERE category_id = ?', [$id]);
        DB::run('UPDATE categories SET parent_id = NULL WHERE parent_id = ?', [$id]);
        DB::delete('categories', 'id = :id', ['id' => $id]);
        Audit::log('categoria.eliminar', 'category', $id, []);
        Flash::ok('Categoría eliminada. Sus productos quedaron sin categoría.');
        redirect('/panel/categorias');
    }

    // --------------------------------------------------------------- marcas
    public function brands(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $name = mb_substr(Request::str('name'), 0, 120);
            if ($name === '') {
                Flash::error('Escriba el nombre de la marca.');
                redirect('/panel/marcas');
            }
            $data = [
                'name'       => $name,
                'slug'       => slugify($name),
                'website'    => filter_var(Request::str('website'), FILTER_VALIDATE_URL) ? mb_substr(Request::str('website'), 0, 190) : null,
                'sort'       => Request::int('sort'),
                'active'     => Request::bool('active') ? 1 : 0,
            ];
            $f = Uploader::files('logo');
            if ($f) {
                $res = Uploader::image($f[0], 'marcas', 600, 400);
                if ($res) {
                    $data['logo'] = $res['path_webp'] ?: $res['path'];
                }
            }
            if ($id && Brand::find($id)) {
                DB::update('brands', $data, 'id = :id', ['id' => $id]);
            } else {
                $exists = DB::one('SELECT id FROM brands WHERE slug = ? LIMIT 1', [$data['slug']]);
                if ($exists) {
                    Flash::error('Ya existe una marca con ese nombre.');
                    redirect('/panel/marcas');
                }
                DB::insert('brands', $data);
            }
            Audit::log('marca.guardar', 'brand', $id, ['nombre' => $name]);
            Flash::ok('Marca guardada.');
            redirect('/panel/marcas');
        }
        $this->view('panel/brands', [
            'title'  => 'Marcas que distribuye',
            'brands' => Brand::all(),
            'edit'   => Request::int('editar') ? Brand::find(Request::int('editar')) : null,
        ], 'layout/panel');
    }

    public function brandDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        DB::run('UPDATE products SET brand_id = NULL WHERE brand_id = ?', [(int) $params['id']]);
        DB::delete('brands', 'id = :id', ['id' => (int) $params['id']]);
        Flash::ok('Marca eliminada.');
        redirect('/panel/marcas');
    }

    // ---------------------------------------------------- atributos técnicos
    public function attributes(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN, Auth::ROLE_SELLER);
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $label = mb_substr(Request::str('label'), 0, 90);
            if ($label === '') {
                Flash::error('Escriba la etiqueta del atributo (ej. Material).');
                redirect('/panel/atributos');
            }
            $type = Request::str('type');
            if (!in_array($type, ['texto', 'numero', 'lista', 'booleano'], true)) {
                $type = 'texto';
            }
            $opts = array_values(array_filter(array_map('trim', explode("\n", Request::str('options')))));
            $catId = Request::int('category_id') ?: null;
            if ($catId && !Category::find($catId)) {
                $catId = null;
            }
            $data = [
                'category_id' => $catId,
                'code'        => mb_substr(Request::str('code') ?: slugify($label, '_'), 0, 50),
                'label'       => $label,
                'type'        => $type,
                'unit'        => mb_substr(Request::str('unit'), 0, 20) ?: null,
                'options'     => $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : null,
                'filterable'  => Request::bool('filterable') ? 1 : 0,
                'sort'        => Request::int('sort'),
            ];
            if ($id && AttributeDef::find($id)) {
                DB::update('attribute_defs', $data, 'id = :id', ['id' => $id]);
            } else {
                DB::insert('attribute_defs', $data);
            }
            Flash::ok('Atributo guardado.');
            redirect('/panel/atributos');
        }
        $this->view('panel/attributes', [
            'title'   => 'Atributos técnicos',
            'attrs'   => AttributeDef::all(),
            'options' => Category::options(),
            'edit'    => Request::int('editar') ? AttributeDef::find(Request::int('editar')) : null,
        ], 'layout/panel');
    }

    public function attributeDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        DB::delete('product_attributes', 'attribute_id = :a', ['a' => (int) $params['id']]);
        DB::delete('attribute_defs', 'id = :id', ['id' => (int) $params['id']]);
        Flash::ok('Atributo eliminado.');
        redirect('/panel/atributos');
    }

    // ------------------------------------------------------ listas de precios
    public function priceLists(array $params = []): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        if (Request::isPost()) {
            Csrf::verify();
            $id = Request::int('id');
            $name = mb_substr(Request::str('name'), 0, 90);
            if ($name === '') {
                Flash::error('Escriba el nombre de la lista.');
                redirect('/panel/listas-precios');
            }
            $data = [
                'name'         => $name,
                'discount_pct' => max(0, min(90, Request::float('discount_pct'))),
                'is_default'   => Request::bool('is_default') ? 1 : 0,
            ];
            if ($data['is_default']) {
                DB::run('UPDATE price_lists SET is_default = 0');
            }
            if ($id && DB::one('SELECT id FROM price_lists WHERE id = ?', [$id])) {
                DB::update('price_lists', $data, 'id = :id', ['id' => $id]);
            } else {
                DB::insert('price_lists', $data);
            }
            Audit::log('lista_precios.guardar', 'price_list', $id, ['nombre' => $name]);
            Flash::ok('Lista de precios guardada.');
            redirect('/panel/listas-precios');
        }
        $this->view('panel/price-lists', [
            'title' => 'Listas de precios',
            'lists' => DB::all('SELECT pl.*, (SELECT COUNT(*) FROM customers cu WHERE cu.price_list_id = pl.id) AS clientes FROM price_lists pl ORDER BY pl.name'),
            'edit'  => Request::int('editar') ? DB::one('SELECT * FROM price_lists WHERE id = ?', [Request::int('editar')]) : null,
        ], 'layout/panel');
    }

    public function priceListDelete(array $params): void
    {
        [$u, $c] = $this->panel(Auth::ROLE_ADMIN);
        $this->guardPost();
        DB::run('UPDATE customers SET price_list_id = NULL WHERE price_list_id = ?', [(int) $params['id']]);
        DB::delete('product_prices', 'price_list_id = :l', ['l' => (int) $params['id']]);
        DB::delete('price_lists', 'id = :id', ['id' => (int) $params['id']]);
        Flash::ok('Lista eliminada.');
        redirect('/panel/listas-precios');
    }
}
